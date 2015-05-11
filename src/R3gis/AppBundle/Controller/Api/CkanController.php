<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Filesystem\Filesystem;
use R3gis\AppBundle\Ckan\CkanUtils;
use R3gis\AppBundle\Ckan\CkanCacheUtils;
use R3gis\AppBundle\Ckan\CkanImportUtils;
use R3gis\AppBundle\Ckan\CkanDataAnalyzerUtils;
use R3gis\AppBundle\Exception\ApiException;

/**
 * @Route("/ckan")
 */
class CkanController extends Controller {

    // @TODO: Move in configuration
    const CKAN_BASE_URL = 'https://data.geobi.info/';

    //const CKAN_BASE_URL = 'http://demo.ckan.org/';

    /**
     * @Route("/packages.json", methods = {"GET"}, name="r3gis.api.ckan.packages")
     */
    public function getPackagesAction(Request $request) {

        $response = new JsonResponse();

        // @TODO: Move to configuration
        $baseUrl = CkanController::CKAN_BASE_URL;

        $kernel = $this->get('kernel');
        $cachePath = $kernel->getRootDir() . '/cache/' . $kernel->getEnvironment();

        $ckan = new CkanUtils($baseUrl, $cachePath);
        $ckan->setLogger($this->get('logger'));
        $packages = $ckan->getPackageList();

        // Randomly remove http cache files
        $ckan->purgePackageList(2);

        // Purge cache also with unused packages
        $ckanCache = new \R3gis\AppBundle\Ckan\CkanCacheUtils($this->getDoctrine());
        $ckanCache->setLogger($this->get('logger'));
        $ckanCache->purge($packages);

        $packages = $ckan->sanitarizePackageList($packages);
        $response->setData(array(
            'success' => true,
            'total' => count($packages),
            'result' => $packages
        ));
        //print_r($packages); die();

        return $response;
    }

    /**
     * @Route("/{package}/{id}/tables.json", methods = {"GET"}, name="r3gis.api.ckan.package_tables")
     */
    public function getPackageTables(Request $request, $package, $id) {

        $response = new JsonResponse();

        // @TODO: Move to configuration
        $baseUrl = CkanController::CKAN_BASE_URL;

        $kernel = $this->get('kernel');
        $cachePath = $kernel->getRootDir() . '/cache/' . $kernel->getEnvironment();
        $tempPath = $kernel->getRootDir() . '/cache/' . $kernel->getEnvironment() . '/tmp/';
        
        $fs = new Filesystem();
        if (!$fs->exists($tempPath)) {
            $fs->mkdir($tempPath);            
        }

        //$ckanPackage = $request->query->get('package');
        //$ckanId = $request->query->get('id');

        $ckan = new CkanUtils($baseUrl, $cachePath);
        $ckan->setLogger($this->get('logger'));
        $ckanCache = new CkanCacheUtils($this->getDoctrine());
        $ckanCache->setLogger($this->get('logger'));
        $ckanCache->purge();

        $data = $ckan->getPackageDataFromPackageAndId($package, $id);

        if (!$ckanCache->hasValidEntry($package, $id, $data['last_modified'])) {
            list($format, $isZip) = $ckan->getDataFormat($package, $id);
            $destFile = "{$tempPath}ckan_" . date('Ymd_His') . '-' . md5(microtime(true) + rand(0, 65535)) . ".{$format}";
            if ($isZip) {
                $destFile = "{$destFile}.zip";
            }

            try {
                $ckan->downloadData($package, $id, $destFile);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                throw new ApiException(ApiException::NOT_FOUND, "Download error", array($destFile));
            }
            if (!in_array($format, array('csv', 'xls', 'shp'))) {
                throw New \Exception("unknown format \"{$format}\"");
            }
            $destTable = 'impexp.import_' . date('Ymd_His') . '_' . md5(microtime(true) + rand(0, 65535));

            $this->importFile($destFile, $destTable, $package, $id, $data['last_modified']);

            $fs->remove($destFile);
        }

        $db = $this->getDoctrine()->getConnection();
        $sql = "SELECT it.it_id, it_sheet, it_ckan_valid, it_is_shape, it_shape_prj_status, itd_column, itd_name, itd_spatial_data, itd_numeric_data 
                   FROM geobi.import_tables it
                   INNER JOIN geobi.import_tables_detail itd ON it.it_id=itd.it_id
                   WHERE it_ckan_package=:it_ckan_package AND it_ckan_id=:it_ckan_id
                   ORDER BY it_ckan_valid, it_sheet, it_id, itd_id, itd_name";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('it_ckan_package' => $package, 'it_ckan_id' => $id));
        $lastSheetId = 0;
        $sheetId = -1;
        $result = array();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            //print_r($row);
            if ($lastSheetId <> $row['it_id']) {
                $lastSheetId = $row['it_id'];
                $sheetId++;
                $result[$sheetId] = array(
                    'code' => $row['it_id'],
                    'name' => $row['it_sheet'],
                    'is_valid' => $row['it_ckan_valid'],
                    'is_shape' => $row['it_is_shape'],
                    'shape_prj_status' => self::decodeShapePrjStatus($row['it_shape_prj_status']));
            }

            $addField = $row['itd_column'] <> '__pk__';
            if ($row['it_is_shape'] && in_array($row['itd_column'], array('gid', 'the_geom'))) {
                $addField = false;
            }
            if ($addField) {
                $result[$sheetId]['headers'][] = array('column' => $row['itd_column'],
                    'name' => $row['itd_name'],
                    'spatial_data' => $row['itd_spatial_data'],
                    'numeric_data' => $row['itd_numeric_data']);
            }
        }

        $response->setData(array(
            'success' => true,
            'total' => count($result),
            'result' => $result
        ));


        return $response;
    }

    /**
     * @Route("/{package}/{id}/{table}/data.json", requirements={"table" = ".*"}, defaults={"table" = null}, methods = {"GET"}, name="r3gis.api.ckan.table_data")
     * #@Cache(expires="+600 seconds", maxage="600", smaxage="600")
     */
    public function getTableDataAction(Request $request, $package, $id, $table) {
        //echo "[$package, $id, $table]";

        $response = new JsonResponse();
        
        $limit = 5; // SS: Move to configuartion
        
        $db = $this->getDoctrine()->getConnection();
        $sql = "SELECT it.it_id, it_schema, it_table, it_sheet, it_ckan_valid, it_is_shape, it_shape_prj_status, itd_column, itd_name, itd_spatial_data, itd_numeric_data 
                   FROM geobi.import_tables it
                   INNER JOIN geobi.import_tables_detail itd ON it.it_id=itd.it_id
                   WHERE it_ckan_package=:it_ckan_package AND it_ckan_id=:it_ckan_id AND it.it_id=:it_id
                   ORDER BY itd_spatial_data DESC, itd_id";
        // echo $sql;
        $stmt = $db->prepare($sql);
        $stmt->execute(array('it_ckan_package' => $package, 'it_ckan_id' => $id, 'it_id'=>(int)$table));
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $headers = array();
        $rows = array();
        
        $fields = array();
        $table = null;
        foreach($data as $headerDef) {
            $table = "{$headerDef['it_schema']}.{$headerDef['it_table']}";
            if ($headerDef['it_is_shape'] && in_array($headerDef['itd_column'], array('gid', 'the_geom'))) {
                continue;
            }
            $headers[] = array(
                'code'=>$headerDef['itd_column'],
                'name'=>$headerDef['itd_name'],
                'type'=>$headerDef['itd_numeric_data']?'numeric':'string');
            $fields[] = $headerDef['itd_column'];
        }
        
        $tot = 0;
        if (!empty($table)) {
            $tot = $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $fieldsList = implode(', ', $fields);
            $sql = "SELECT $fieldsList FROM {$table} LIMIT {$limit}";
            foreach( $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $rows[] = array_values($row);
            }
        }
        
        $result = array(
            'header' => $headers,
            'rows' => $rows);

        $response->setData(array(
            'success' => true,
            'total' => $tot,
            'result' => $result
        ));
        return $response;
    }
    
    
    

    /**
     * #@Route("/load.json", methods = {"POST"}, name="r3gis.api.import.package_info")
     */
    /* public function applyImportFromCkanAction(Request $request, $name, $_format) {

      $response = new JsonResponse();
      try {
      $ckanPackage = $request->query->get('package');
      $ckanId = $request->query->get('id');
      $sheet = $request->query->get('sheet');
      $spatialColumn = $request->query->get('spatial_column');
      $dataColumn = $request->query->get('data_column');
      $lang = $request->query->get('lang');

      if ($dataColumn == '') {
      $dataColumn = null;
      }


      $db = $this->getDoctrine()->getConnection();

      $db->beginTransaction();

      $mapUtils = new MapCreatorUtils($this->getDoctrine());
      $hash = $mapUtils->createEmptyMap(1, $lang); // 1, $ckanPackage, $ckanId, $sheet, $spatialColumn, $dataColumn, $lang);
      $layer = $mapUtils->createLayerDataFromCache($ckanPackage, $ckanId, $sheet, $spatialColumn, $dataColumn);
      $mapUtils->setDefaults($layer);

      $db->commit();

      $result = array('hash' => $hash);
      $response->setData(array(
      'success' => true,
      'result' => $result
      ));
      } catch (\Exception $e) {
      // throw $e;
      $response->setData(array(
      'success' => false,
      'error' => $e->getMessage()
      ));
      }
      return $response;
      } */

    /* private function getMapLayers(Map $map) {

      $db = $this->getDoctrine()->getManager()->getConnection();
      $divisionTypeList = $db->query("SELECT dt_id, dt_code FROM geobi.division_type")->fetchAll(\PDO::FETCH_KEY_PAIR);
      $layerTypeList = $db->query("SELECT lt_id, lt_code FROM geobi.layer_type")->fetchAll(\PDO::FETCH_KEY_PAIR);


      $result = null;

      $mapLayers = $this->getDoctrine()
      ->getRepository('R3gisAppBundle:MapLayer')
      ->findBy(array('map' => $map), array('order' => 'ASC'));

      foreach ($mapLayers as $mapLayer) {
      $divisionType = null;
      if ( !empty( $divisionTypeList[$mapLayer->getDivisionTypeId()] ) ) {
      $divisionType = $divisionTypeList[$mapLayer->getDivisionTypeId()];
      }
      $layer = array(
      'order' => $mapLayer->getOrder(),
      'name' => $mapLayer->getName(),
      'layer_type' => $layerTypeList[$mapLayer->getLayerTypeId()],
      'division_type' => $divisionType,
      'divisions' => $mapLayer->getDivisions(),
      'precision' => $mapLayer->getPrecision(),
      'unit' => $mapLayer->getUnit(),
      'nodata_color' => $mapLayer->getNoDataColor(),
      'temporary' => $mapLayer->getTemporary(),
      'opacity' => $mapLayer->getOpacity(),
      'outline_color' => $mapLayer->getOutlineColor(),
      'class' => $this->getClass($mapLayer),
      'extent'=>$this->getLayerExtent($mapLayer)
      );



      $result[] = $layer;
      }
      return $result;
      } */

    /**
     * #@Route("/{name}.{_format}", defaults={"name" = "add_layer", "_format" = "json"}, requirements={"name" = "add_layer[\-\w]*", "_format" = "json"}, methods = {"GET"})
     */
    /* public function addLayerFromCkanAction(Request $request, $name, $_format) {

      $response = new JsonResponse();
      try {
      $ckanPackage = $request->query->get('package');
      $ckanId = $request->query->get('id');
      $sheet = $request->query->get('sheet');
      $spatialColumn = $request->query->get('spatial_column');
      $dataColumn = $request->query->get('data_column');
      $lang = $request->query->get('lang');
      $duplicate = $request->query->get('duplicate') && $request->query->get('duplicate') === 'true';
      $hash = $request->query->get('hash');

      $map = $this->getDoctrine()->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
      if (empty($map)) {
      throw new \Exception("Map not found");
      }

      if ( $duplicate ) {
      // Duplicate the current map and return the new hash. Set the temporary flag
      $mapUtils = new MapCreatorUtils($this->getDoctrine());
      $user = $this->getDoctrine()
      ->getRepository('R3gisAppBundle:User')
      ->find(1);  // Utente cha salva. Preso da autenticazione
      $hash = $mapUtils->duplicateMap($user, $hash, true );
      $map = $this->getDoctrine()->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
      }

      if ($dataColumn == '') {
      $dataColumn = null;
      }


      $db = $this->getDoctrine()->getConnection();

      $db->beginTransaction();

      $mapUtils = new MapCreatorUtils($this->getDoctrine());
      $mapUtils->setMap($map);
      $mapUtils->setLang($map->getLanguage()->getId());
      //$hash = $mapUtils->createEmptyMap(1, $lang); // 1, $ckanPackage, $ckanId, $sheet, $spatialColumn, $dataColumn, $lang);
      $layer = $mapUtils->createLayerDataFromCache($ckanPackage, $ckanId, $sheet, $spatialColumn, $dataColumn);
      $mapUtils->setDefaults($layer);
      $db->commit();

      // Return map informations (Use utility, not forward to encode/decode)
      $jsonResponse = $this->forward('R3gisAppBundle:Map:edit', array(
      'hash' => $hash,
      'name' => 'map',
      '_format' => 'json'
      ));
      $jsonData = json_decode($jsonResponse->getContent(), true);

      $result = array(
      'hash' => $hash,
      //'layer' => $result[] = MapController::getMapLayer($this->getDoctrine(), $layer)
      'map' => $jsonData['result']['map']);

      $response->setData(array(
      'success' => true,
      'result' => $result
      ));
      } catch (\Exception $e) {
      // throw $e;
      $response->setData(array(
      'success' => false,
      'error' => $e->getMessage()
      ));
      }
      return $response;
      } */

    /**
     * #@Route("/{name}.{_format}", defaults={"name" = "del_layer", "_format" = "json"}, requirements={"name" = "remove_layer[\-\w]*", "_format" = "json"}, methods = {"GET"})
     */
    /* public function delLayerFromCkanAction(Request $request, $name, $_format) {

      $response = new JsonResponse();
      try {
      $duplicate = $request->query->get('duplicate');
      $hash = $request->query->get('hash');
      $order = $request->query->get('layer');

      $map = $this->getDoctrine()->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
      if (empty($map)) {
      throw new \Exception("map not found");
      }

      if ( $request->request->get('duplicate') && $request->request->get('duplicate') === 'true' ) {
      // Duplicate the current map and return the new hash. Set the temporary flag
      $mapUtils = new MapCreatorUtils($this->getDoctrine());
      $user = $this->getDoctrine()
      ->getRepository('R3gisAppBundle:User')
      ->find(1);  // Utente cha salva. Preso da autenticazione
      $hash = $mapUtils->duplicateMap($user, $hash, true );
      }

      $layer = $this->getDoctrine()->getRepository('R3gisAppBundle:MapLayer')->findOneBy(array('map'=>$map, 'order'=>$order));
      if (empty($layer)) {
      throw new \Exception("maplayer not found");
      }

      $db = $this->getDoctrine()->getConnection();
      $db->beginTransaction();

      $em = $this->getDoctrine()->getManager();
      $em->remove($layer);
      $em->flush();

      // Reorder layers
      $sql = "UPDATE geobi.map_layer
      SET ml_order = q.sort_order
      FROM (
      SELECT ml_id, ml_order, Row_Number() OVER (ORDER BY ml_order) as sort_order
      FROM geobi.map_layer
      WHERE map_id = :map_id) q
      WHERE q.ml_id = map_layer.ml_id";
      $stmt = $db->prepare($sql);
      $stmt->execute(array($map->getId()));
      $db->commit();

      // Return map informations (Use utility, not forward to encode/decode)
      $jsonResponse = $this->forward('R3gisAppBundle:Map:edit', array(
      'hash' => $hash,
      'name' => 'map',
      '_format' => 'json'
      ));
      $jsonData = json_decode($jsonResponse->getContent(), true);

      $result = array(
      'hash' => $request->query->get('hash'),
      //'layer' => $result[] = MapController::getMapLayer($this->getDoctrine(), $layer)
      'map' => $jsonData['result']['map']);

      $response->setData(array(
      'success' => true,
      'result' => $result
      ));
      } catch (\Exception $e) {
      // throw $e;
      $response->setData(array(
      'success' => false,
      'error' => $e->getMessage()
      ));
      }
      return $response;
      } */

    // @TODO: Move to utility class
    private function importFile($destFile, $fqDestTable, $ckanPackage, $ckanId, $lastModified) {
        set_time_limit(5 * 60);

        $db = $this->getDoctrine()->getConnection();
        $db->beginTransaction();

        // Asynchronous transaction
        $db->exec("SET LOCAL synchronous_commit TO OFF");
        
        $kernel = $this->get('kernel');
        $tempPath = $kernel->getRootDir() . '/cache/' . $kernel->getEnvironment() . '/tmp/';

        $opt = array(
            'temp_path' => $tempPath,
            'database_driver' => $this->container->getParameter('database_driver'),
            'database_host' => $this->container->getParameter('database_host'),
            'database_port' => $this->container->getParameter('database_port'),
            'database_name' => $this->container->getParameter('database_name'),
            'database_user' => $this->container->getParameter('database_user'),
            'database_password' => $this->container->getParameter('database_password'));

        $ckanCache = new CkanCacheUtils($this->getDoctrine());
        $ckanCache->setLogger($this->get('logger'));

        $ckanImport = new CkanImportUtils($this->getDoctrine(), $opt);
        $data = $ckanImport->importFile($destFile, $fqDestTable);

        $ckanCache->lockEntry($ckanPackage, $ckanId);

        $shapeList = array();
        foreach ($data as $importDetail) {
            if ($importDetail['tot_records'] == 0) {
                continue;
            }
            // print_r($importDetail);

            $ckanAnalyzer = new CkanDataAnalyzerUtils($this->getDoctrine());
            $analydedData = $ckanAnalyzer->analyze($importDetail['table']);
            // print_r($analydedData);

            $isValidTable = $analydedData['info']['tot_records'] > 0 &&
                    ($analydedData['info']['has_spatial_data'] === true || $analydedData['info']['is_shape_file'] === true) &&
                    ($analydedData['info']['has_numeric_data'] === true || $analydedData['info']['is_shape_file'] === true);

            // map details data
            $headersDef = array();
            foreach ($importDetail['headers'] as $headerKey => $headerName) {
                $headersDef[$headerKey] = $analydedData['columns_info'][$headerKey];
                $headersDef[$headerKey]['name'] = $headerName;
            }

// echo setlocale(LC_MESSAGES, '0'); die();
// echo $db->getWrappedConnection()->query('SHOW client_encoding')->fetchColumn(); die();

            $ckanCache->addEntry(array('table' => $importDetail['table'],
                'sheet' => $importDetail['name'],
                'ckan_package' => $ckanPackage,
                'ckan_id' => $ckanId,
                'ckan_last_modified' => $lastModified,
                'is_valid' => $isValidTable,
                'is_shape' => $analydedData['info']['is_shape_file'],
                'shape_prj_status' => $importDetail['prj_status']), $headersDef);
            if ($analydedData['info']['is_shape_file']) {
                $shapeList[] = $importDetail['table'];
            }
        }
        $db->commit();

        foreach ($shapeList as $table) {
            $this->force2D($table);
            $this->reproject($table, 3857);
            $this->repairGeometry($table);
        }
    }

    private function force2D($fqDestTable) {
        $dim = 2;

        $db = $this->getDoctrine()->getConnection();
        $db->beginTransaction();

        $sql = "ALTER TABLE {$fqDestTable} DROP CONSTRAINT IF EXISTS enforce_dims_the_geom";
        $db->exec($sql);

        $sql = "UPDATE {$fqDestTable} SET the_geom=st_force_2d(the_geom) WHERE ST_NDims(the_geom)<>2";
        $db->exec($sql);

        $sql = "ALTER TABLE {$fqDestTable} ADD CONSTRAINT enforce_dims_the_geom CHECK (st_ndims(the_geom) = 2)";
        $db->exec($sql);

        $sql = "SELECT populate_geometry_columns('{$fqDestTable}'::regclass)";
        $db->exec($sql);

        $db->commit();
    }

    private function reproject($fqDestTable, $destSrid) {
        $defaultsSrid = array(4326, 25832, 25833, 3003); // Transform data without srid to data with srid
        $db = $this->getDoctrine()->getConnection();
        // $db->beginTransaction();         // not in transaction

        $srid = $db->query("SELECT st_srid(the_geom) FROM {$fqDestTable} LIMIT 1")->fetchColumn();
        $sql = "ALTER TABLE {$fqDestTable} DROP CONSTRAINT IF EXISTS enforce_srid_the_geom";
        $db->exec($sql);
        if ($srid <= 0) {
            $done = false;
            foreach ($defaultsSrid as $srid) {
                try {
                    $sql = "UPDATE {$fqDestTable} SET the_geom=st_transform(st_setsrid(the_geom, {$srid}), {$destSrid})";
                    $db->exec($sql);
                    break;
                } catch (\Exception $e) {
                    // SS LOG!!!
                }
            }
        } else {
            $sql = "UPDATE {$fqDestTable} SET the_geom=st_transform(the_geom, {$destSrid})";
            $db->exec($sql);
        }

        $sql = "ALTER TABLE {$fqDestTable} ADD CONSTRAINT enforce_srid_the_geom CHECK (st_srid(the_geom) = ({$destSrid}))";
        $db->exec($sql);

        $sql = "SELECT populate_geometry_columns('{$fqDestTable}'::regclass)";
        $db->exec($sql);
    }

    private function repairGeometry($fqDestTable) {

        $db = $this->getDoctrine()->getConnection();
        $db->beginTransaction();

        $geometrType = $db->query("SELECT geometrytype(the_geom) FROM {$fqDestTable} LIMIT 1")->fetchColumn();

        $sql = "ALTER TABLE {$fqDestTable} DROP CONSTRAINT IF EXISTS enforce_is_valid_the_geom";
        $db->exec($sql);

        if ($geometrType == 'MULTIPOLYGON') {
            $sql = "UPDATE {$fqDestTable} SET the_geom=st_multi(st_buffer(the_geom, 0)) WHERE NOT st_isvalid(the_geom)";
            $db->exec($sql);
        }

        $sql = "ALTER TABLE {$fqDestTable} ADD CONSTRAINT enforce_is_valid_the_geom CHECK (st_isvalid(the_geom))";
        $db->exec($sql);

        $db->commit();
    }

    // SS: Move to utility
    private static function decodeShapePrjStatus($statusCode) {
        if ($statusCode == '') {
            return 'valid';
        }
        switch ($statusCode) {
            case 'V': return 'valid';
            case 'I': return 'invalid';
            case 'M': return 'missing';
        }
        throw new \Exception("Unknown code \"{$statusCode}\"");
    }

}

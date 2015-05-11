<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use R3gis\AppBundle\Utils\MapCreatorUtils;
use R3gis\AppBundle\Entity\Map;
use R3gis\AppBundle\Entity\MapLayer;
use R3gis\AppBundle\Entity\MapClass;
use R3gis\AppBundle\Utils\Division\DivisionFactory;
use R3gis\AppBundle\Exception\ApiException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use R3gis\AppBundle\Ckan\CkanDataAnalyzerUtils;
use R3gis\AppBundle\Ckan\CkanUtils;
use R3gis\AppBundle\Ckan\CkanCacheUtils;

/**
 * @Route("/map")
 */
class MapController extends Controller {

    const LIMIT = 50;  // Max 50 rows
    const DEFAULT_EXTENT = '737779 4231225 2061823 5957355';

    /**
     * @api {get} /map/maps.json  Map list
     * @apiName listAction
     * @apiGroup Map
     *
     * @apiDescription Return the list of the public maps and 
     *                 the authenticated user's maps.
     * @apiParam (Filters) {String} [q]          Filter the result on map's name or description
     * @apiParam (Filters) {String} [language]   Filter result for the given language. 
     *                                          Valid language are en=english, de=german, it=italian
     *                                          Multiple space-separated values allowed
     * @apiParam (Filters) {boolean} [onlyMine] if "true" return only the maps of the current user
     * 
     * @apiParam (Order/Limit) {string} [order]      Order of the resultset. 
     *                                               Accepted values: "recent" (more recent) and "click" (more clicked maps)
     *                                               Multiple space-separated values allowed
     * @apiParam (Order/Limit) {integer} [limit=50]  Limit the number of results
     * @apiParam (Order/Limit) {integer} [offset]    Return the result starting from 0-based offset
     * 
     */
        
    /**
     * @Route("/maps.json", methods = {"GET"}, name="r3gis.api.map.list")
     */
    public function listAction(Request $request) {

        $request = $this->purgeRequest($request);
        
        $response = new JsonResponse();

        $em = $this->getDoctrine()->getManager();
        $qb = $em->getRepository("R3gisAppBundle:Map")->createQueryBuilder('m');
        
        $user = $this->getUser();

        if($user!=null) {

            if ($request->query->get('onlyMine') == 'true') {
                $qb->andWhere("IDENTITY(m.user)=:userid");  // Only my maps
                $qb->setParameter('userid',$user->getId());
            } else {
                $qb->andWhere("m.private=FALSE OR IDENTITY(m.user)=:userid");  // All public maps + my maps
                $qb->setParameter('userid',$user->getId());
            }
        }else{
            $qb->andWhere("m.private=FALSE");  // if no user, show Only public maps
        }
        
        // Filters
        $qb->andWhere("m.temporary=FALSE");                       // Exclude temporary map   
        if ($request->query->get('q')) { // Text search
            // Implement ILIKE
            $qb->andWhere("UPPER(m.name) LIKE UPPER(:text) OR UPPER(m.description) LIKE UPPER(:text)");
            $qb->setParameter('text', '%' . $request->query->get('q') . '%');
        }
        if ($request->query->get('language')) { // Language filter (multiple values allowed)
            $qb->andWhere("m.language IN (:language)");
            $qb->setParameter('language', explode(' ', $request->query->get('language')));
        }
        

        // Count record (before order and limit)
        $qb2 = clone($qb); // Clone needed...
        $total = $qb2->select('COUNT(m)')
                ->getQuery()
                ->getSingleScalarResult();

        // Order by
        if ($request->query->get('order')) { // Order by (multiple values allowed)
            $orders = explode(' ', $request->query->get('order'));
            foreach ($orders as $ord) {
                switch ($ord) {
                    case 'recent':
                        $qb->addOrderBy('m.modDate', 'DESC');
                        break;
                    case 'click':
                        $qb->addOrderBy('m.clickCount', 'DESC');
                        break;
                        break;
                }
            }
        } else {
            $qb->addOrderBy('m.name', 'ASC');
            $qb->addOrderBy('m.id', 'ASC');
        }

        // Limit
        if ($request->query->get('limit') > 0) {
            $qb->setMaxResults($request->query->get('limit'));
        } else {
            $qb->setMaxResults(self::LIMIT);
        }

        // Offset
        if ($request->query->get('offset') > 0) {
            $qb->setFirstResult($request->query->get('offset'));
        }

        $result = $qb
                ->getQuery()
                ->getResult();

        // Custom normalization of datetime
        $normalizer = new GetSetMethodNormalizer();
        $callback = function ($dateTime) {
            return $dateTime instanceof \DateTime ? $dateTime->format(\DateTime::ISO8601) : '';
        };
        $normalizer->setCallbacks(array('insDate' => $callback, 'modDate' => $callback));

        $serializer = new Serializer(array($normalizer), array(new JsonEncoder()));
        $jsonData = $serializer->normalize($result);
        
        //add flag "isMine" to maps, and remove user entity from data.
        foreach ($jsonData as $key => $map) {
            $isMine = false;
            if($user && $user->getId()==$map["user"]["id"]) {
                $isMine=true;
            }
            $jsonData[$key]["isMine"]=$isMine;
            $jsonData[$key]["user"] = $jsonData[$key]["user"]["name"];
        }

        $response->setData(array(
            'success' => true,
            'total' => $total,
            'result' => $jsonData
        ));

        return $response;
    }

    /**
     * @api {get} /map/{hash}/map.json  Map info
     * @apiName infoAction
     * @apiGroup Map
     *
     * @apiDescription Return the detail map info for the given hash. See map response json for detail
     * 
     */
    
    /**
     * @Route("/{hash}/map.json", methods = {"GET"}, name="r3gis.api.map.info")
     */
    public function infoAction(Request $request, $hash) {

        $request = $this->purgeRequest($request);
        
        $response = new JsonResponse();

        // solo se non mia
        $this->addClickCount($hash);

        $response->setData(array(
            'success' => true,
            'result' => $this->getMapInfo($hash)
        ));
        return $response;
    }

    /**
     * @api {post} /map         Add map
     * @apiName addAction
     * @apiGroup Map
     *
     * @apiDescription Add a new empty map with default values and no layer. Return the new-created map data
     * 
     */
    
    /**
     * @Route("", methods = {"POST"}, name="r3gis.api.map.add")
     */
    public function addAction(Request $request) {
        
        if(!$this->get('security.context')->isGranted('ROLE_MAP_PRODUCER')) {
            throw new AccessDeniedException("Only logged in users are allowed to create maps.");
        }
        $user = $this->getUser();

        $lang = $request->request->get('language', 'en');
        $temporary = $request->request->get('temporary') == 'true';

        $response = new JsonResponse();
        $db = $this->getDoctrine()->getConnection();

        $mapUtils = new MapCreatorUtils($this->getDoctrine());
        $hash = $mapUtils->createEmptyMap($user, $lang, $temporary);

        $response->setData(array(
            'success' => true,
            'result' => $this->getMapInfo($hash)
        ));

        return $response;       
    }

    /**
     * @api {put} /map/{hash}         Modify map
     * @apiName modAction
     * @apiGroup Map
     *
     * @apiDescription Update the given map or optionally store changes to a new temporary map.
     *                 Input data is the map-info data. Return the map data (old or new depende on duplicate parameter).
     *                 Temporary maps are deleted after 24 hours
     * 
     * @apiParam {boolean} [duplicate]      if "true" save the changes to a new temporary map. Old map is NOT changed.
     *                                      NB: The user.data tables are NON duplicated
     *                                      This parameter must be set on the url
     * @apiParam {boolean} [purge]          if "true" remove all temporary maps based on the given hash (cascade).
     *                                      Final saving. "duplicate" parameter must be false.
     *                                      This parameter must be set on the url
     * @apiParam {string} [copyFromHash]    if given, replace the original data with the data of the map with this hash, 
     *                                      then apply the request update (on the url hash).
     *                                      "duplicate" parameter must be false
     *                                      This parameter must be set on the body
     * @apiParam {json} [map]               the data in map-info json format to store
     *                                      This parameter must be set on the body
     *                                      
     */
    
    /**
     * @Route("/{hash}", methods = {"PUT"}, name="r3gis.api.map.mod")
     */
    public function modAction(Request $request, $hash) {

        if(!$this->get('security.context')->isGranted('ROLE_MAP_PRODUCER')) {
            throw new AccessDeniedException("Only logged in users are allowed to modify maps.");
        }
        
        $request = $this->purgeRequest($request);
        $map = $request->request->get('map', array());
        $duplicate = $request->request->get('duplicate');

        $response = new JsonResponse();

        $db = $this->getDoctrine()->getConnection();
        $db->beginTransaction();

        $copyFromHash = $request->request->get('copyFromHash');
        if (!empty($copyFromHash) && $copyFromHash <> $hash) {
            $user = $this->getUser();
            $mapUtils = new MapCreatorUtils($this->getDoctrine());
            $hash = $mapUtils->replaceLayersFromMap($user, $hash, $copyFromHash);
            $hash = $this->updateMap($hash, $map, false);
        } else {
            $hash = $this->updateMap($hash, $map, $duplicate);
        }


        if ($request->request->get('purge') === 'true') {
            $this->purgeTemporaryMap($hash, false);
        }

        $db->commit();

        $response->setData(array(
            'success' => true,
            'result' => $this->getMapInfo($hash)
        ));

        return $response;
     
    }

    /**
     * @api {post} /map/{hash}         Copy map
     * @apiName copyAction
     * @apiGroup Map
     *
     * @apiDescription         Copy the map with the given hash to a new one. Return the map-info data of the new map
     *                         NB: The user.data tables are duplicated
     * 
     * @apiParam {boolean} [temporary]      if "true" the new created map is a temporary map
     *                                      
     */
    
    /**
     * @Route("/{hash}", methods = {"POST"}, name="r3gis.api.map.copy")
     */
    public function copyAction(Request $request, $hash) {
        $request = $this->purgeRequest($request);
        
        if(!$this->get('security.context')->isGranted('ROLE_MAP_PRODUCER')) {
            throw new AccessDeniedException("Only logged in users are allowed to copy maps.");
        }

        // SS: Verificare che salvo la mia mappa.
        $user = $this->getUser();

        $language = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:Language')
                ->find($request->request->get('language', 'en'));
        if (empty($language)) {
            throw new \Exception("Missing language");
        }

        $response = new JsonResponse();

        $db = $this->getDoctrine()->getConnection();
        $db->beginTransaction();

        $mapUtils = new MapCreatorUtils($this->getDoctrine());
        $newHash = $mapUtils->duplicateMap($user, $hash, false);

        $map = $this->getDoctrine()->getRepository('R3gisAppBundle:Map')->findOneByHash($newHash);
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }

        $map->setLanguage($language);
        $em = $this->getDoctrine()->getManager();
        $em->persist($map);
        $em->flush();
        $em->detach($map);
        $db->commit();

        //@TODO: Duplica data table!!!

        // $result = array('hash' => $newHash);
        $response->setData(array(
            'success' => true,
            'result' => $this->getMapInfo($newHash)
        ));

        return $response;
    }

    /**
     * @api {delete} /map/{hash}         Delete map
     * @apiName delAction
     * @apiGroup Map
     *
     * @apiDescription Remove the given map and cascade all the related temporary maps 
     *                 and the related user.data tables.
     *                                      
     */
    /**
     * @Route("/{hash}", methods = {"DELETE"}, name="r3gis.api.map.del")
     */
    public function delAction(Request $request, $hash) {

        if(!$this->get('security.context')->isGranted('ROLE_MAP_PRODUCER')) {
            throw new AccessDeniedException("Only logged in users are allowed to delete maps.");
        }
        
        $oldMapInfo = $this->getMapInfo($hash);
            
        $user = $this->getUser();
        if($oldMapInfo["map"]["user"]["id"] != $user->getId() && !$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException("Only the owner of the map or admins are allowed to delete this map.");
        }
        
        
        $db = $this->getDoctrine()->getConnection();

        $db->beginTransaction();
        
        // Remove all temporary map entry related to the given hash, and unused tables too (if not used)
        $this->purgeTemporaryMap($hash, true);

        $db->commit();
        
        $response = new JsonResponse();
        $response->setData(array(
            'success' => true,
            'result' => $oldMapInfo
        ));

        return $response;
    }
    
    // Return the last_modified field for the given cKan data. Return false or DateTime
    private function searchCkanModDate(array $cKanPackageList, $cKanPackage, $ckanId) {
        
        foreach($cKanPackageList as $package) {
            if ($package['id'] == $cKanPackage) {
                foreach($package['resources'] as $resource) {
                    if ($resource['id'] == $ckanId) {
                        return \DateTime::createFromFormat('Y-m-d\TH:i:s', $resource['last_modified']);
                    }
                }
            }
        }
        return null;
    }
    
    /**
     * @api {get} /map/{hash}/update_check.json  Check cKan for data changes
     * @apiName checkUpdateAction
     * @apiGroup Map
     *
     * @apiDescription Return the layer list with data update
     * 
     */
    
    /**
     * @Route("/{hash}/update_check.json", methods = {"GET"}, name="r3gis.api.map.update_check")
     */
    public function checkUpdateAction(Request $request, $hash) {
        
        $baseUrl = CkanController::CKAN_BASE_URL;
        
        $layers = array();
        $result = array();
        
        $response = new JsonResponse();
        try {
            $db = $this->getDoctrine()->getConnection();
            $kernel = $this->get('kernel');
            $cachePath = $kernel->getRootDir() . '/cache/' . $kernel->getEnvironment();

            $ckan = new CkanUtils($baseUrl, $cachePath);
            $ckan->setLogger($this->get('logger'));
        
            $packages = array();
            $sql = "SELECT ml_order, ml_ckan_package, ml_ckan_id, ml_mod_date, ml_ckan_sheet, ml_data_column, ml_spatial_column
                    FROM geobi.map
                    INNER JOIN geobi.map_layer USING(map_id)
                    WHERE map_hash=:map_hash";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('map_hash' => $hash));
            
            foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $packages[$row['ml_ckan_package']] = $row['ml_ckan_package'];
                $layers[] = $row;
            }
            $packages = $ckan->sanitarizePackageList(array_values($packages), true);
        
            
            foreach($layers as $curLayer) {
                $cKanModDate = $this->searchCkanModDate($packages, $curLayer['ml_ckan_package'], $curLayer['ml_ckan_id']);
                $curLayerModDate = \DateTime::createFromFormat('Y-m-d H:i:s', $curLayer['ml_mod_date']);
                if ($cKanModDate > $curLayerModDate) {
                    $result[] = array(
                        'order' => $curLayer['ml_order'],
                        'ckan_old_date' => $curLayer['ml_mod_date'],
                        'ckan_new_date' => $cKanModDate->format('Y-m-d H:i:s'),
                        'package' => $curLayer['ml_ckan_package'],
                        'ckan_id' => $curLayer['ml_ckan_id'],
                        'sheet' => $curLayer['ml_ckan_sheet'],
                        'data_column' => $curLayer['ml_data_column'],
                        'spatial_column' => $curLayer['ml_spatial_column']);
                    
                    // Remove temporary data
                    $sql = "DELETE FROM geobi.import_tables WHERE it_ckan_package=:it_ckan_package AND it_ckan_id=:it_ckan_id AND it_ckan_date<=:it_ckan_date";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array('it_ckan_package' => $curLayer['ml_ckan_package'], 
                                         'it_ckan_id' => $curLayer['ml_ckan_id'],
                                         'it_ckan_date' => $cKanModDate->format('Y-m-d H:i:s')));
                }
            }

            if (count($result) > 0) {
                $ckanCache = new CkanCacheUtils($this->getDoctrine());
                $ckanCache->setLogger($this->get('logger'));
                $ckanCache->purge();
            }    
        
        
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

    }

    /**
     * @api {get} /map/{hash}/stat/{order}/info.json         Map statistic info
     * @apiName statInfoAction
     * @apiGroup Map
     *
     * @apiDescription         Return the statistic info of the map. Resturn a list of data (actualy max 1 row)
     * 
     * @apiParam {float} x         the longitude (in map coordinate)
     * @apiParam {float} y         the latitude (in map coordinate)
     * @apiParam {float} [buffer]  (not implemented) the buffer to apply to the point to find the data
     *                                      
     */
    
    /**
     * @Route("/{hash}/stat/{order}/info.json", methods = {"GET"}, name="r3gis.api.map.layer_info")
     */
    public function statInfoAction(Request $request, $hash, $order) {
        $db = $this->getDoctrine()->getConnection();
        $response = new JsonResponse();
        try {

            $x = (float) $request->query->get('x');
            $y = (float) $request->query->get('y');
            $buffer = (int) $request->query->get('buffer');

            //$buffer = $request->query->get('buffer');
            //$limit = $request->query->get('limit');
            //$bbox = $request->query->get('bbox');
            $geom = null;
            if (!empty($x) && !empty($y)) {
                $geom = "ST_GeometryFromText('POINT({$x} {$y})')";
            }
            if (empty($geom)) {
                throw new \Exception('Intersection geometry empty');
            }

            $mapInfo = $this->getMapLayerInfo($hash, $order);
            $geom = "ST_SetSrid({$geom}, 3857)";

            if (empty($mapInfo)) {
                throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
            }

            // Check MapStatController
            $fields = array();
            $fields[] = 'gid';

            if (!empty($mapInfo['data_column'])) {
                $fields[] = $mapInfo['data_column'];
            }

            if ($mapInfo['is_shape']) {
                $sql = "SELECT * FROM {$mapInfo['schema']}.{$mapInfo['table']} WHERE ST_intersects(the_geom, {$geom})";
            } else {
                $geoTableName = empty($mapInfo['area_type_code']) ? 'area' : "area_part_{$mapInfo['area_type_code']}";
                $langQuoted = $db->quote($mapInfo['lang']);
                if ($mapInfo['layer_type'] == 'point' && $buffer > 0) {
                    $sql = "SELECT *, st_distance(ST_PointOnSurface(the_geom), {$geom}) AS distance_from_geometry
                            FROM {$mapInfo['schema']}.{$mapInfo['table']} u
                            INNER JOIN data.{$geoTableName} USING (ar_id)
                            LEFT JOIN geobi.localization ON ar_name_id=msg_id AND lang_id={$langQuoted}
                            WHERE st_distance(ST_PointOnSurface(the_geom), {$geom}) < {$buffer}
                            ORDER BY distance_from_geometry ASC
                            LIMIT 1";
                } else {
                    $sql = "SELECT *, st_distance(ST_PointOnSurface(the_geom), {$geom}) AS distance_from_geometry
                            FROM {$mapInfo['schema']}.{$mapInfo['table']} u
                            INNER JOIN data.{$geoTableName} USING (ar_id)
                            LEFT JOIN geobi.localization ON ar_name_id=msg_id AND lang_id={$langQuoted}
                            WHERE ST_intersects(the_geom, {$geom})
                            LIMIT 1";
                }
                //$sql .= " ORDER BY ";
            }
            $result = array();
            foreach ($db->query($sql, \PDO::FETCH_ASSOC) as $row) {
                $result[] = array(
                    'id' => $row['gid'],
                    'name' => empty($row['loc_text']) ? null : $row['loc_text'],
                    'data' => empty($row[$mapInfo['data_column']]) ? null : $row[$mapInfo['data_column']],
                );
            }

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
    }

    /**
     * @api {get} /map/extent.json         Map extent
     * @apiName getExtentAction
     * @apiGroup Map
     *
     * @apiDescription         Return the default map extent
     * 
     */
    
    /**
     * @Route("/extent.json", methods = {"GET"}, name="r3gis.api.map.extent")
     * @Cache(expires="+600 seconds", maxage="600", smaxage="600")
     */
    public function getExtentAction(Request $request) {
        $response = new JsonResponse();
        $extent = explode(' ', MapController::DEFAULT_EXTENT);
        $response->setData(array(
            'success' => true,
            'result' => $extent
        ));

        return $response;
    }

    // SS: Move to utility
    static public function getLayerExtent($doctrine, MapLayer $mapLayer) {

        $db = $doctrine->getManager()->getConnection();

        $result = null;
        $fqTable = $mapLayer->getTableSchema() . '.' . $mapLayer->getTableName();
        $sql = null;
        $mapLayer->getIsShape();
        if ($mapLayer->getIsShape()) {
            $sql = "SELECT st_extent(the_geom) FROM {$fqTable}";
        } else {
            $sql = "SELECT st_extent(the_geom) FROM {$fqTable} INNER JOIN data.area using(ar_id)";
        }
        if (!empty($sql)) {
            $box = $db->query($sql)->fetchColumn();
            if (!empty($box)) {
                foreach (explode(',', str_replace(array('BOX(', ')', ' '), array('', '', ','), $box)) as $val) {
                    $result[] = round($val);
                }
            }
        }
        return $result;
    }

    // SS: Move to utility
    static public function getClass($doctrine, MapLayer $mapLayer, $divisionType) {
        $result = null;

        $mapClasses = $doctrine
                ->getRepository('R3gisAppBundle:MapClass')
                ->findBy(array('mapLayer' => $mapLayer), array('order' => 'ASC'));
        //$divisionType
        // echo count($mapClasses);
        foreach ($mapClasses as $classNo => $mapClass) {
            if (($classNo == 0) ||
                 ($divisionType == 'manual') ||
                 ($classNo == count($mapClasses) - 1)
                ) {
            $color = $mapClass->getColor();
            $color = empty($color) ? null : "#{$color}";
            $class = array(
                'order' => $mapClass->getOrder(),
                'name' => $mapClass->getName(),
                'number' => $mapClass->getNumber(),
                'text' => $mapClass->getText(),
                'color' => $color,
            );
            $result[] = $class;
                }
        }
        return $result;
    }
    
    // SS: Move to utility
    static public function getLegend($doctrine, MapLayer $mapLayer) {
        $result = null;

        $mapClasses = $doctrine
                ->getRepository('R3gisAppBundle:MapClass')
                ->findBy(array('mapLayer' => $mapLayer), array('order' => 'ASC'));
        foreach ($mapClasses as $mapClass) {
            $color = $mapClass->getColor();
            $color = empty($color) ? null : "#{$color}";
            $class = array(
                'order' => $mapClass->getOrder(),
                'name' => $mapClass->getName(),
                'number' => $mapClass->getNumber(),
                'text' => $mapClass->getText(),
                'color' => $color,
            );
            $result[] = $class;
        }
        return $result;
    }

    // SS: Move to utility
    public static function getMapLayer($doctrine, MapLayer $mapLayer) {
        static $divisionTypeList, $layerTypeList;

        if ($divisionTypeList == null) {
            $db = $doctrine->getManager()->getConnection();
            $divisionTypeList = $db->query("SELECT dt_id, dt_code FROM geobi.division_type")->fetchAll(\PDO::FETCH_KEY_PAIR);
            $layerTypeList = $db->query("SELECT lt_id, lt_code FROM geobi.layer_type")->fetchAll(\PDO::FETCH_KEY_PAIR);
        }

        $divisionType = null;
        if (!empty($divisionTypeList[$mapLayer->getDivisionTypeId()])) {
            $divisionType = $divisionTypeList[$mapLayer->getDivisionTypeId()];
        }

        $layer = array(
            'order' => $mapLayer->getOrder(),
            'name' => $mapLayer->getName(),
            'layerType' => $layerTypeList[$mapLayer->getLayerTypeId()],
            'divisionType' => $divisionType,
            'divisions' => $mapLayer->getDivisions(),
            'precision' => $mapLayer->getPrecision(),
            'unit' => $mapLayer->getUnit(),
            'nodataColor' => $mapLayer->getNoDataColor(),
            'temporary' => $mapLayer->getTemporary(),
            'opacity' => $mapLayer->getOpacity(),
            'outlineColor' => $mapLayer->getOutlineColor(),
            'minSize' => $mapLayer->getMinSize(),
            'maxSize' => $mapLayer->getMaxSize(),
            'sizeType' => $mapLayer->getSizeType(),
            'symbol' => $mapLayer->getSymbol(),
            'active' => $mapLayer->getActive(),
            'class' => MapController::getClass($doctrine, $mapLayer, $divisionType),
            'legend' => MapController::getLegend($doctrine, $mapLayer),
            'extent' => MapController::getLayerExtent($doctrine, $mapLayer)
        );

        return $layer;
    }

    private function getMapLayers(Map $map) {
        $result = array();
        
        $em = $this->getDoctrine()->getManager();
        $mapLayers = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:MapLayer')
                ->findBy(array('map' => $map), array('order' => 'ASC'));
                
        foreach ($mapLayers as $mapLayer) {
            $em->refresh($mapLayer);
            
            $layerInfo = MapController::getMapLayer($this->getDoctrine(), $mapLayer);
            $result[] = array(
                'name' => $layerInfo['name'],
                'order' => $layerInfo['order'],
                'type' => 'statistic',
                'active' => $layerInfo['active'],
                'options' =>
                array_diff_key($layerInfo, array_fill_keys(array(
                    'name',
                    'order'), null
            )));
        }
        return $result;
    }

    // SS: Move to utility class (Used by MapPreviewController)
    private function getMapExtent(Map $map) {

        $db = $this->getDoctrine()->getManager()->getConnection();

        $result = null;

        $mapLayers = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:MapLayer')
                ->findBy(array('map' => $map));

        $sqlPart = array();
        foreach ($mapLayers as $mapLayer) {
            $fqTable = $mapLayer->getTableSchema() . '.' . $mapLayer->getTableName();
            if ($mapLayer->getIsShape()) {
                $sqlPart[] = "SELECT the_geom FROM {$fqTable}";
            } else {
                $sqlPart[] = "SELECT the_geom FROM {$fqTable} INNER JOIN data.area using(ar_id)";
            }
        }
        if (count($sqlPart) > 0) {
            $sql = "SELECT st_extent(the_geom) FROM (" . implode(" UNION ", $sqlPart) . ") AS foo";
            $box = $db->query($sql)->fetchColumn();
            if (!empty($box)) {
                foreach (explode(',', str_replace(array('BOX(', ')', ' '), array('', '', ','), $box)) as $val) {
                    $result[] = round($val);
                }
            }
        }
        return $result;
    }

    // COPIATO DA MapStatControlle. MOVING AWAY...
    private function getMapLayerInfo($hash, $order) {

        $db = $this->getDoctrine()->getManager()->getConnection();
        $sql = "SELECT ml_id AS id, ml_order AS order, lang_id AS lang, lt_code AS layer_type, ml_schema AS schema, ml_table AS table, ml_is_shape AS is_shape, 
                       f_geometry_column AS geometry_column, type AS geomery_type, srid, ml_data_column AS data_column, 
                       at.at_id AS area_type_id, LOWER(at_code) AS area_type_code, ml_opacity AS opacity,
                       '#' || ml_nodata_color AS nodata_color, '#' || ml_outline_color AS outline_color, 
                       ml_symbol AS symbol, ml_min_size AS min_size, ml_max_size AS max_size, ml_size_type AS size_type
                FROM geobi.map m
                INNER JOIN geobi.map_layer l ON m.map_id=l.map_id
                INNER JOIN geobi.layer_type lt ON l.lt_id=lt.lt_id
                LEFT JOIN data.area_type at ON l.at_id=at.at_id
                LEFT JOIN public.geometry_columns g ON ml_is_shape IS TRUE AND f_table_schema=ml_schema and f_table_name=ml_table and f_geometry_column='the_geom'
                WHERE map_hash=:map_hash AND ml_order=:ml_order";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('map_hash' => $hash, 'ml_order' => $order));
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            if (!$data['is_shape']) {
                $data['geomery_type'] = 'MULTIPOLYGON';
                $data['geometry_column'] = 'the_geom';
                $data['srid'] = 3857;
            }
            $sql = "SELECT mc_name AS name, mc_order AS order, mc_number AS number, mc_text AS text, '#' || mc_color AS color
                    FROM geobi.map_class 
                    WHERE ml_id={$data['id']}
                    ORDER BY mc_order";
            $class = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($class)) {
                $data['class'] = $class;
            } else {
                $data['class'] = array();
            }
        }

        return $data;
    }

    private function addClickCount($hash) {
        $map = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:Map')
                ->findOneByHash($hash);
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }

        // Click count (Only if not my map)
        $map->addClickCount();
        $em = $this->getDoctrine()->getManager();
        $em->persist($map);
        $em->flush();
        $em->detach($map);
    }

    // Return the map information from hash. Used to output data to client
    private function getMapInfo($hash) {

        $map = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:Map')
                ->findOneByHash($hash);
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }

        $extent = $this->getMapExtent($map);
        if (empty($extent)) {
            $extent = explode(' ', MapController::DEFAULT_EXTENT);
        }

        // Custom normalization of datetime
        $normalizer = new GetSetMethodNormalizer();
        $callback = function ($dateTime) {
            return $dateTime instanceof \DateTime ? $dateTime->format(\DateTime::ISO8601) : '';
        };
        $normalizer->setCallbacks(array('insDate' => $callback, 'modDate' => $callback));

        $serializer = new Serializer(array($normalizer), array(new JsonEncoder()));
        $mapData = $serializer->normalize($map);

        $backgroundLayer = array(
            'name' => null,
            'order' => 0,
            'type' => 'background',
            'active' => $mapData['backgroundActive'],
            'options' => array('source' => $mapData['backgroundType']));

        $mapInfo = array();
        $mapInfo['name'] = $mapData['name'];
        $mapInfo['description'] = $mapData['description'];
        $mapInfo['private'] = $mapData['private'];
        $mapInfo['temporary'] = $mapData['temporary'];
        $mapInfo['displayProjection'] = 'EPSG:3857';
        $mapInfo['extent'] = $extent;
        $mapInfo['userExtent'] = $mapData['userExtent'];
        $mapInfo['layers'] = array_merge(array($backgroundLayer), $this->getMapLayers($map));
        $mapInfo['clickCount'] = $mapData['clickCount'];
        $mapInfo['language'] = $mapData['language']['id'];
        $mapInfo['user'] = array('id'=> $mapData['user']['id'],'name' => $mapData['user']['name']);
        if( $this->get('security.context')->isGranted('ROLE_MAP_PRODUCER') && 
            $this->getUser()->getId() == $mapInfo['user']['id']) {
            $mapInfo['isMine'] = true;
        }else {
            $mapInfo['isMine'] = false;
        }        
        $mapData['map'] = $mapInfo;

        // unset some info
        foreach (array('language', 'user', 'idParent', 'clickCount', 'userExtent', 'backgroundType', 'description', 'name', 'id', 'insDate', 'modDate', 'private', 'temporary') as $delKey) {
            unset($mapData[$delKey]);
        }

        return $mapData;
    }

    // Save the map data
    private function updateMap($hash, array $data, $duplicate) {

        if(!$this->get('security.context')->isGranted('ROLE_MAP_PRODUCER')) {
            throw new AccessDeniedException("Only logged in users are allowed to copy maps.");
        }
        $user = $this->getUser();

        if (empty($data)) {
            throw new \Exception('Missing data');
        }
        if (empty($data['layers'])) {
            throw new \Exception('Missing layer node');
        }

        $db = $this->getDoctrine()->getConnection();

        $divisionTypeList = $db->query("SELECT dt_code, dt_id FROM geobi.division_type")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $layerTypeList = $db->query("SELECT lt_code, lt_id  FROM geobi.layer_type")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $em = $this->getDoctrine()->getManager();
        
        $map = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:Map')
                ->findOneByHash($hash);
        
        if($map->getUser()->getId() != $user->getId()) {
            throw new AccessDeniedException("Only the owner of the map can modify this map.");
        }
        
        $mapUtils = new MapCreatorUtils($this->getDoctrine());
        if ($duplicate) {
            // Duplicate the current map and return the new hash. Set the temporary flag
            $hash = $mapUtils->duplicateMap($user, $hash, true);
            $map = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:Map')
                ->findOneByHash($hash);
        }
        
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }
        
        // TODO Check for missing node
        $map->setName($data['name']);
        $map->setDescription($data['description']);
        $map->setPrivate($data['private']);
        $map->setUserExtent($data["userExtent"]);

        $em->persist($map);
        
        // Save layer data
        $presentLayers = array();
        foreach ($data['layers'] as $layerNode) {
            if ($layerNode['type'] == 'background') {
                $map->setBackgroundActive($layerNode['active']);
                $map->setBackgroundType($layerNode['options']['source']);
                continue;
            }
            if ($layerNode['type'] == 'statistic') {
                $presentLayers[] = $layerNode['order'];
                $mapLayer = $this->getDoctrine()
                        ->getRepository('R3gisAppBundle:MapLayer')
                        ->findOneBy(array('map' => $map, 'order' => $layerNode['order']));
                if (empty($mapLayer)) {
                    throw new ApiException(ApiException::NOT_FOUND, 'Layer not found');
                }
                $mapLayer->setName($layerNode['name']);
                if (!empty($layerTypeList[$layerNode['options']['layerType']])) {
                    $mapLayer->setLayerTypeId($layerTypeList[$layerNode['options']['layerType']]);
                }

                if (!empty($divisionTypeList[$layerNode['options']['divisionType']])) {
                    $mapLayer->setDivisionTypeId($divisionTypeList[$layerNode['options']['divisionType']]);
                }

                $mapLayer->setDivisions($layerNode['options']['divisions']);
                $mapLayer->setNoDataColor($layerNode['options']['nodataColor']);
                $mapLayer->setOutlineColor($layerNode['options']['outlineColor']);

                $mapLayer->setOpacity(min(100, max(0, $layerNode['options']['opacity'])));  // 0..100
                $mapLayer->setPrecision(empty($layerNode['options']['precision']) ? null : (int) $layerNode['options']['precision'] );  //null, 0..9
                $mapLayer->setUnit($layerNode['options']['unit']);
                $mapLayer->setMinSize($layerNode['options']['minSize']);
                $mapLayer->setMaxSize($layerNode['options']['maxSize']);
                $mapLayer->setSizeType($layerNode['options']['sizeType']);
                $mapLayer->setSymbol($layerNode['options']['symbol']);
                $mapLayer->setActive($layerNode['active']);
                //SS: Fonte dei dati
                //SS: Nome di default in creazione: Nome colonna dati
                $em->persist($mapLayer);

                if (!empty($divisionTypeList[$layerNode['options']['divisionType']]) && !empty($layerNode['options']['class']) && count($layerNode['options']['class']) >= 2) {

                    // SS: Move elsewhere...
                    $sql = "DELETE FROM geobi.map_class WHERE ml_id=" . $mapLayer->getId();
                    $db->exec($sql);
                    if ($layerNode['options']['divisionType'] == 'manual') {
                        $order = 1;
                        $layerNode['options']['class'][count($layerNode['options']['class']) - 1]['number'] = $layerNode['options']['class'][count($layerNode['options']['class']) - 2]['number'];
                        foreach ($layerNode['options']['class'] as $classNode) {
                            $mapClass = new MapClass();
                            $mapClass->setMapLayer($mapLayer)
                                    ->setOrder($order)
                                    ->setNumber($classNode['options']['number'])
                                    ->setColor($classNode['options']['color']);
                            $em->persist($mapClass);
                            $order++;
                        }
                    } else {
                        // Calculate 
                        if ($mapLayer->getDataColumn() != '') {
                            $sql = "SELECT " . $mapLayer->getDataColumn() . " FROM " . $mapLayer->getTableSchema() . '.' . $mapLayer->getTableName();
                            $dataColumn = $db->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
                            $limits = DivisionFactory::get($layerNode['options']['divisionType'])->getLimits($dataColumn, $mapLayer->getDivisions(), $mapLayer->getPrecision());
                            if (count($limits) > 0) {
                                $colors = MapCreatorUtils::getStatsColors(count($limits) + 1, $layerNode['options']['class'][0]['color'], $layerNode['options']['class'][1]['color']);
                                $order = 1;
                                foreach ($limits as $value) {
                                    $mapClass = new MapClass();
                                    $mapClass->setMapLayer($mapLayer)
                                            ->setOrder($order)
                                            ->setNumber($value)
                                            ->setColor($colors[$order - 1]);
                                    $em->persist($mapClass);
                                    $order++;
                                }

                                $mapClass = new MapClass();
                                $mapClass->setMapLayer($mapLayer)
                                        ->setOrder($order)
                                        ->setNumber($value)
                                        ->setColor($colors[$order - 1]);

                                $em->persist($mapClass);
                                $order++;
                            }
                        }
                    }
                }
            }
        }
        
        // Remove not submitted layers
        $totDBLayers = $db->query("SELECT COUNT(*) FROM geobi.map_layer WHERE map_id={$map->getId()}")->fetchColumn();
        for ($layerNo = 1; $layerNo <= $totDBLayers; $layerNo++) {
            if (!in_array($layerNo, $presentLayers)) {
                $mapLayer = $this->getDoctrine()
                        ->getRepository('R3gisAppBundle:MapLayer')
                        ->findOneBy(array('map' => $map, 'order' => $layerNo));
                $em->remove($mapLayer);
            }
        }
        $em->persist($map);  // SS: Salvo tipo sfondo?
        $em->flush();
        $em->detach($map);
        
        $mapUtils->reorderLayers($hash);

        return $hash;
    }

    // Save the map data
    private function purgeTemporaryMap($hash, $purgeCurrent) {

        // SS: Verificare che salvo la mia mappa.
        // Per il salvataggio: Duplico, elimino l'originale, modifico hash, update dei dati che arrivano dalla form
        $user = $this->getUser();

        $mapUtils = new MapCreatorUtils($this->getDoctrine());
        $mapUtils->purgeTemporaryMap($user, $hash, $purgeCurrent);
    }
    
    
    // SS: Utility class or extend controller
    // If the request is application/json replace parameters
    protected function purgeRequest(Request $request) {

        // @see http://silex.sensiolabs.org/doc/cookbook/json_request_body.html for post request in application
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }
        return $request;
    }
    
    
    /**
     * @api {get} /map/{hash}/data/{order}/data.json         getUserData
     * @apiName getTableUserDataAction
     * @apiGroup MapData
     *
     * @apiDescription Get data from table
     *
     * @apiParam {string} [spatialMatch]  if is 'false', limit the results to data where the spatialName does not match, if is 'true' only returns matching data.
     *                                      
     */
    /**
     * @Route("/{hash}/data/{order}/data.json", methods = {"GET"}, name="r3gis.api.map.data")
     */
    public function getTableUserDataAction(Request $request, $hash, $order) {
        $spatialMatch = $request->query->get('spatialMatch');  // SS: TODO: Rivedere nome!!!
        $limit = (int)$request->query->get('limit');
        $offset = (int)$request->query->get('offset');
        
        //$map = $this->getDoctrine()
        //        ->getRepository('R3gisAppBundle:Map')
        //        ->findOneByHash($hash);
        //if (empty($map)) {
        //    throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        //}
        //$mapLayers = $this->getDoctrine()
        //        ->getRepository('R3gisAppBundle:MapLayer')
        //        ->findOneByHashAndOrder(array('hash' => $hash, 'order'=>$order));
        
        $db = $this->getDoctrine()->getManager()->getConnection();
        $sql = "SELECT ml_is_shape, ml_schema, ml_table, ml_spatial_column, ml_data_column, ml_spatial_column_header, ml_data_column_header
                FROM geobi.map
                INNER JOIN geobi.map_layer USING (map_id)
                WHERE map_hash=:map_hash AND ml_order=:ml_order";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('map_hash' => $hash, 'ml_order' => $order));
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        $rows = array();
        if (!$data['ml_is_shape']) {
            // TODO: Salvare e riprendere i nomi delle colonne spaziali

            $header = array(
                'spatialColumn'=>$data['ml_spatial_column_header'],
                'dataColumn'=> $data['ml_data_column_header'],
            );
                
            $sql = "SELECT gid, ar_id IS NOT NULL AS spatial_match, {$data['ml_spatial_column']} AS spatial_column, {$data['ml_data_column']} AS data_column 
                    FROM {$data['ml_schema']}.{$data['ml_table']}
                    WHERE 1=1";
            if ($spatialMatch == 'false') {
                $sql .= " AND ar_id IS NULL ";
            } else if ($spatialMatch == 'true') {
                $sql .= " AND ar_id IS NOT NULL ";
            }
            $total = $db->query("SELECT COUNT(*) as total from (".$sql.") as count")->fetchColumn(0);
            
            if ($limit > 0) {
                $sql .= " LIMIT {$limit} ";
                if ($offset > 0) {
                    $sql .= " OFFSET {$offset} ";
                }
            }
            foreach($db->query($sql, \PDO::FETCH_ASSOC) as $row) {
                $rows[] = array(
                    'id'=>$row['gid'], 
                    'spatialMatch'=>$row['spatial_match'], 
                    'spatialColumn'=>$row['spatial_column'], 
                    'dataColumn'=>$row['data_column']);
            }
            
            $result = array(
                "success"=>true,
                "total"=> $total,
                "result"=>array(
                    "header"=>$header,
                    "rows"=>$rows,
                ),
            );
            $response = new JsonResponse();
            $response->setStatusCode(200);
            $response->setData($result);
            return $response;
        }

    }
    
    /**
     * @api {put} /map/{hash}/data/{order}/data.json         updateUserData
     * @apiName updateTableUserDataAction
     * @apiGroup MapData
     *
     * @apiDescription set elements from data table
     *
     * @apiParam {json} updateField json must be an array, even if it only contains one element.  
     *                                      
     */
    /**
     * @Route("/{hash}/data/{order}/data.json", methods = {"PUT"}, name="r3gis.api.map.data_update")
     */
    public function updateTableUserDataAction(Request $request, $hash, $order) {
        // data.json dovrebbe essere un array
        $dataArray = json_decode($request->getContent());

        if(!is_array($dataArray)) {
            throw new BadRequestHttpException("json data should be an array.");
        }
        
        $db = $this->getDoctrine()->getManager()->getConnection();
        $sql = "SELECT ml_schema, ml_table, ml_spatial_column, ml_data_column
                FROM geobi.map
                INNER JOIN geobi.map_layer USING (map_id)
                WHERE map_hash=:map_hash AND ml_order=:ml_order";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('map_hash' => $hash, 'ml_order' => $order));
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $db->beginTransaction();
        $sql = "UPDATE {$data['ml_schema']}.{$data["ml_table"]} SET
                    ar_id=:ar_id,
                    {$data["ml_data_column"]}=:data,
                    {$data["ml_spatial_column"]}=:spatial
                WHERE gid=:id";
        $stmt = $db->prepare($sql);
        foreach($dataArray as $key=>$value) {
            $stmt->execute(array(
                'data'=>$value->dataColumn,
                'spatial'=>$value->spatialColumn,
                'id'=>$value->id,
                'ar_id'=>$value->spatialCode,
            ));
        }

        $db->commit();
        
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setData(array(
            "success"=>true,
            "total"=>count($dataArray)
        ));
        return $response;
    }
    
    /**
     * @api {get} /map/{hash}/data/{order}/complete.json         autocomplete
     * @apiName completeSpatialDataAction
     * @apiGroup MapData
     *
     * @apiDescription get available spatialNames/Codes for this userData
     *
     * @apiParam {string} q  search term, at least 2 characters
     *                                      
     */
    /**
     * @Route("/{hash}/data/{order}/complete.json", methods = {"GET"}, name="r3gis.api.map.data_autocomplete")
     */
    public function completeSpatialDataAction(Request $request, $hash, $order) {
        $q = $request->query->get('q');
        
        if($q==null || strlen($q)<2) {
            throw new BadRequestHttpException("Search term must be at least 2 Characters long.");
        }
        
        $db = $this->getDoctrine()->getManager()->getConnection();
        $sql = "SELECT ml_schema, ml_table, ml_spatial_column, ml_data_column
                FROM geobi.map
                INNER JOIN geobi.map_layer USING (map_id)
                WHERE map_hash=:map_hash AND ml_order=:ml_order";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('map_hash' => $hash, 'ml_order' => $order));
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $ckanAnalyzer = new CkanDataAnalyzerUtils($this->getDoctrine());
        $columntype = $ckanAnalyzer->getBetterGeometryColumnType($data["ml_schema"] .".". $data["ml_table"], $data["ml_spatial_column"]);
        
        // Ricavo tipo geometria (comune, provincia, regione, nazione)
        //select at_code
        //from data.userdata_20141212_e31b8d83be1e697e7319ca1dd9f9808a
        //inner join data.area using (ar_id)
        //inner join data.area_type using (at_id)
        // limit 1
                
        
        // ricerca:
        $sql = "
        select ar_id as key, loc_text as value, 0 as priority 
        from data.area 
        inner join geobi.localization on ar_name_id=msg_id
        inner join data.area_type using (at_id)
        where loc_text ilike :like1 and lang_id=:lang and at_code=:geo

        union 

        select ar_id as key, loc_text as value, 1 as priority
        from data.area 
        inner join geobi.localization on ar_name_id=msg_id
        inner join data.area_type using (at_id)
        where loc_text ilike :like2 and lang_id=:lang and at_code=:geo and not loc_text ilike :like1


        order by priority, value
        limit 10";
        
        $like1 = "{$q}%";
        $like2 = "%{$q}%";
        $geo = $columntype[0];
        $lang = $columntype[1];
                
        $stmt = $db->prepare($sql);
        $stmt->execute(
            array(
                'like1' => $like1,
                'like2' => $like2,
                'lang' => $lang,
                'geo' => $geo,
            )
        );
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setData(
            array(
                'success'=>true,
                'result'=> $result
            )
        );
        return $response;
    }

}

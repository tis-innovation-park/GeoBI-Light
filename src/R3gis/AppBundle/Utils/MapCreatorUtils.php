<?php

namespace R3gis\AppBundle\Utils;

use R3gis\AppBundle\Ckan\CkanDataAnalyzerUtils;
use R3gis\AppBundle\Entity\Map;
use R3gis\AppBundle\Entity\MapLayer;
use R3gis\AppBundle\Entity\MapClass;
use R3gis\AppBundle\Utils\Division\DivisionFactory;
use R3gis\AppBundle\Exception\ApiException;

final class MapCreatorUtils {

    /**
     * @type \Doctrine\ORM\EntityManager
     */
    private $em;
    // Map 
    private $map;

    public function __construct(\Doctrine\Bundle\DoctrineBundle\Registry $doctrine) {

        $this->doctrine = $doctrine;
        $this->em = $doctrine->getManager();
        $this->db = $this->em->getConnection();
    }

    public function setMap(Map $map) {
        $this->map = $map;
    }

    public function setLang($lang) {
        $this->lang = $lang;
    }

    private function getNextLayerOrder() {
        $sql = "SELECT COALESCE(MAX(ml_order), 0) + 1
                FROM geobi.map_layer
                WHERE map_id=:map_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array('map_id' => $this->map->getId()));
        return $stmt->fetchColumn();
    }

    // Check the given package
    private function getPackeageData($ckanPackage, $ckanId, $sheet) {
        $sql = "SELECT it_schema, it_table, it_is_shape 
                FROM geobi.import_tables
                WHERE it_ckan_package=:it_ckan_package AND it_ckan_id=:it_ckan_id AND it_id=:it_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array('it_ckan_package' => $ckanPackage, 'it_ckan_id' => $ckanId, 'it_id' => (int) $sheet));
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (empty($data)) {
            throw new \Exception("Package/id/sheet not found");
        }
        return $data;
    }

    private function checkColumnExists($type, $sheet, $column) {

        $chkMap = array('spatial' => 'itd_spatial_data', 'numeric' => 'itd_numeric_data');
        if (!array_key_exists($type, $chkMap)) {
            throw new \Exception("Unknown type \"{$type}\"");
        }

        $sql = "SELECT COUNT(*)
                FROM geobi.import_tables_detail
                WHERE it_id=:it_id AND itd_column=:itd_column AND {$chkMap[$type]} IS TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array('it_id' => $sheet, 'itd_column' => $column));
        $totRecord = $stmt->fetchColumn();
        if (empty($totRecord)) {
            throw new \Exception("Column not found");
        }
    }

    public function createEmptyMap(\R3gis\AppBundle\Entity\User $user, $lang, $temporary) {

        $this->lang = $lang;
        $language = $this->doctrine
                ->getRepository('R3gisAppBundle:Language')
                ->find($lang);
        if (empty($lang)) {
            throw new \Exception("Missing language");
        }

        $hash = md5(uniqid());
        $this->map = new Map();
        $this->map->setName(DefaultsUtils::getMapName($lang))
                ->setDescription(DefaultsUtils::getMapDescription($lang))
                ->setBackgroundType(DefaultsUtils::getMapBackgroundType($lang))
                ->setBackgroundType('osm')
                ->setTemporary(false)
                ->setHash($hash)
                ->setLanguage($language)
                ->setTemporary($temporary)
                ->setUser($user);

        $em = $this->doctrine->getManager();
        $em->persist($this->map);
        $em->flush();

        return $this->map->getHash();
    }

    public function createLayerDataFromCache($ckanPackage, $ckanId, $sheet, $spatialColumn, $dataColumn) {
        
        $needSpatialColumn = true;
        $needDataColumn = true;
        $destSchema = 'data';
        $destSRID = 3857;
        $geomColumn = 'the_geom';

        $db = $this->db;
        $data = $this->getPackeageData($ckanPackage, $ckanId, $sheet);

        $pkName = $data['it_is_shape'] ? 'gid' : '__pk__';

        $fields = array();

        if ($data['it_is_shape'] && empty($spatialColumn)) {
            $needSpatialColumn = false;   // Geometry column is shape column (not from text column)
            // $fields[] = $geomColumn;
        } else {
            $fields[] = 'ar_id';  // Add FK to data.area table
            $fields[] = $spatialColumn;
        }

        if ($data['it_is_shape'] && !$needSpatialColumn && empty($dataColumn)) {
            $needDataColumn = false;
        }

        if ($needDataColumn) {
            // Try to understand spatial data
            $this->checkColumnExists('numeric', $sheet, $dataColumn);
            $fields[] = $dataColumn;
        }

        if ($needSpatialColumn) {
            // Try to understand spatial data
            $this->checkColumnExists('spatial', $sheet, $spatialColumn);

            $ckanAnalyzer = new CkanDataAnalyzerUtils($this->doctrine);
            list($betterType, $betterLang) = $ckanAnalyzer->getBetterGeometryColumnType("{$data['it_schema']}.{$data['it_table']}", $spatialColumn);
            if (empty($betterType) || empty($betterLang)) {
                throw new \Exception("Data not found");
            }

            $copySql = "WITH 
                        q1 AS (SELECT ar_id, loc_text
                           FROM {$destSchema}.area ar
                           INNER JOIN {$destSchema}.area_type at ON ar.at_id=at.at_id 
                           INNER JOIN geobi.localization ON ar.ar_name_id=msg_id and lang_id='{$betterLang}' AND at_code='{$betterType}')
                        SELECT {$pkName} AS gid, " . implode(', ', $fields) . "
                        FROM {$data['it_schema']}.{$data['it_table']}
                        LEFT JOIN q1 ON UPPER(
                            regexp_replace(unaccent((regexp_split_to_array({$spatialColumn}::TEXT, E'(/)'))[1]), '[^a-zA-Z]', '', 'g')) =
                            UPPER(regexp_replace(unaccent(loc_text), '[^a-zA-Z]', '', 'g'))
                        ORDER BY {$pkName}";
            //echo $copySql; die();
        } else {
            $shapeFields = array();
            $shapeFields[] = "{$pkName} AS gid";
            $shapeFields = array_merge($shapeFields, $fields);
            $shapeFields[] = "st_transform(st_force_2d({$geomColumn}), {$destSRID}) AS {$geomColumn}";
            $copySql = "SELECT " . implode(', ', $shapeFields) . "
                    FROM {$data['it_schema']}.{$data['it_table']}
                    ORDER BY {$pkName}";
        }

        // Create / optimize table
        $destTable = 'userdata_' . date('Ymd') . '_' . md5(microtime(true) + rand(0, 65535));
        $sql = "CREATE TABLE {$destSchema}.{$destTable} AS {$copySql}";
        $db->exec($sql);

        $userId = $this->map->getUser()->getId();
        $sql = "COMMENT ON TABLE {$destSchema}.{$destTable} IS '" . date('Y-m-d H:i:s') . " User #{$userId}'";
        $db->exec($sql);

        // Update fillfactor
        $sql = "ALTER TABLE {$destSchema}.{$destTable} SET (FILLFACTOR=100)";
        $db->exec($sql);

        // Add PK
        $sql = "ALTER TABLE {$destSchema}.{$destTable} ALTER COLUMN gid SET NOT NULL";
        $db->exec($sql);
        $sql = "ALTER TABLE {$destSchema}.{$destTable} ADD CONSTRAINT {$destTable}_pkey PRIMARY KEY (gid) WITH (FILLFACTOR=100)";
        $db->exec($sql);

        if ($needSpatialColumn) {
            // Add FK to area (partition table)
            $betterTypeLower = strtolower($betterType);
            $sql = "ALTER TABLE {$destSchema}.{$destTable} ADD CONSTRAINT {$destTable}_fk FOREIGN KEY (ar_id) REFERENCES {$destSchema}.area_part_{$betterTypeLower}(ar_id) ON DELETE NO ACTION ON UPDATE NO ACTION NOT DEFERRABLE";
            $db->exec($sql);

            // Cluster on PK
            $sql = "CLUSTER {$destSchema}.{$destTable} USING {$destTable}_pkey";
            $db->exec($sql);
        } else {
            $sql = "SELECT populate_geometry_columns('{$destSchema}.{$destTable}'::regclass)";
            $db->exec($sql);

            $sql = "ALTER TABLE {$destSchema}.{$destTable} ADD CONSTRAINT enforce_is_valid_the_geom CHECK (st_isvalid(the_geom))";
            $db->exec($sql);

            // Cluster on spatial data
            $sql = "SELECT true FROM {$destSchema}.{$destTable} WHERE {$geomColumn} IS NULL LIMIT 1";
            $hasNulls = $db->query($sql)->fetchColumn() === true;
            if (!$hasNulls) {
                $sql = "ALTER TABLE {$destSchema}.{$destTable} ALTER COLUMN {$geomColumn} SET NOT NULL";
                $db->exec($sql);
            }
            $sql = "CREATE INDEX {$destTable}_{$geomColumn}_gist ON {$destSchema}.{$destTable} USING gist ({$geomColumn}) WITH (FILLFACTOR=100)";
            $db->exec($sql);
            $sql = "CLUSTER {$destSchema}.{$destTable} USING {$destTable}_{$geomColumn}_gist";
            $db->exec($sql);
        }

        if (!( $data['it_is_shape'] && empty($spatialColumn) )) {
            $areaTypeId = $db->query("SELECT at_id FROM {$destSchema}.area_type WHERE at_code='{$betterType}'")->fetchColumn();
        }

        // Add map entity

        $mapLayer = new MapLayer();
        
        // Get the real name of the column
        $sql = "select d1.itd_name as geo_name, d2.itd_name as data_name
                from geobi.import_tables t 
                left join geobi.import_tables_detail d1 on t.it_id=d1.it_id and d1.itd_column=:itd_geo_column and d1.itd_spatial_data IS TRUE
                left join geobi.import_tables_detail d2 on t.it_id=d2.it_id and d2.itd_column=:itd_data_column and d2.itd_numeric_data IS TRUE
                where it_ckan_package=:it_ckan_package and it_ckan_id=:it_ckan_id and t.it_id=:it_id";
                
        $stmt = $this->db->prepare($sql);
        $params = array(
            'it_ckan_package'=>$ckanPackage, 
            'it_ckan_id'=>$ckanId,
            'it_id'=>$sheet,
            'itd_geo_column'=>$spatialColumn,
            'itd_data_column'=>$dataColumn,            
        );
        $stmt->execute($params);
        $headers = $stmt->fetchAll(\PDO::FETCH_ASSOC );
        $dataHeader = $headers[0]['data_name'];
        $geoHeader = $headers[0]['geo_name'];

        $mapLayer->setMap($this->map)
                ->setName(DefaultsUtils::getMapLayerName($this->lang))
                ->setTableSchema('data')
                ->setTableName($destTable)
                ->setCkanSheet($sheet)
                ->setCkanPackage($ckanPackage)
                ->setCkanId($ckanId)
                ->setOrder($this->getNextLayerOrder())
                ->setDivisions(DefaultsUtils::getMapLayerDivision($this->lang))
                ->setIsShape($data['it_is_shape'])
                ->setDataColumn($dataColumn)
                ->setSpatialColumn($spatialColumn)
                ->setDataColumnHeader($dataHeader)
                ->setSpatialColumnHeader($geoHeader)
                ->setTemporary(false)
                // ->setDataLanguage($betterLang)
                ->setLayerTypeId(DefaultsUtils::getMapLayerType($this->lang))
                ->setOpacity(DefaultsUtils::getMapLayerOpacity($this->lang))
                ->setOutlineColor(DefaultsUtils::getMapLayerOutlineColor($this->lang))
                ->setNoDataColor(DefaultsUtils::getMapLayerNoDataColor($this->lang))
                ->setMinSize(DefaultsUtils::getMapLayerMinSize($this->lang))
                ->setMaxSize(DefaultsUtils::getMapLayerMaxSize($this->lang))
                ->setSizeType(DefaultsUtils::getMapLayerSizeType($this->lang));
        if (!empty($dataColumn)) {
            $mapLayer->setDivisionTypeId(DefaultsUtils::getMapLayerDivisionType($this->lang));
        }

        $em = $this->doctrine->getManager();
        $em->persist($mapLayer);
        $em->flush();

        return $mapLayer;
    }

    /**
     * return the RGB component from a hex string (#RRGGBB)
     * @param string $s     the input color string
     * @param int $r        the red component (0-255)
     * @param int $g        the green component (0-255)
     * @param int $b        the blue component (0-255)
     */
    static private function getRGBColor($s, &$r, &$g, &$b) {
        $s = str_replace('#', '', $s);
        $r = hexdec(substr($s, 0, 2));
        $g = hexdec(substr($s, 2, 2));
        $b = hexdec(substr($s, 4, 2));
    }

    /**
     * Return an array with the statistic color
     * @param int $divisions            the division no
     * @param string $startColor        the start color
     * @param string $endColor          the end color
     */
    static public function getStatsColors($divisions, $startColor, $endColor) {
        $result = array();
        MapCreatorUtils::getRGBColor($startColor, $srartRed, $srartGreen, $srartBlue);
        MapCreatorUtils::getRGBColor($endColor, $endRed, $endGreen, $endBlue);

        if ($divisions <= 1) {
            $divisions = 2;
        }
        $deltaRed = ($endRed - $srartRed) / ($divisions - 1);
        $deltaGreen = ($endGreen - $srartGreen) / ($divisions - 1);
        $deltaBlue = ($endBlue - $srartBlue) / ($divisions - 1);

        for ($i = 0; $i < $divisions; $i++) {
            $result[] = sprintf('%02X', $srartRed) . sprintf('%02X', $srartGreen) . sprintf('%02X', $srartBlue);
            $srartRed += $deltaRed;
            $srartGreen += $deltaGreen;
            $srartBlue += $deltaBlue;
        }
        return $result;
    }

    public function setDefaults(MapLayer $mapLayer) {
        if ($mapLayer->getDataColumn() != null) {

            $db = $this->db;
            $sql = "SELECT " . $mapLayer->getDataColumn() . " FROM " . $mapLayer->getTableSchema() . '.' . $mapLayer->getTableName();
            $data = $db->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
            $divisionType = DivisionFactory::unmapDivision($mapLayer->getDivisionTypeId());
            $limits = DivisionFactory::get($divisionType)->getLimits($data, $mapLayer->getDivisions(), $mapLayer->getPrecision());
            if (count($limits) > 0) {
                $colors = MapCreatorUtils::getStatsColors(count($limits) + 1, DefaultsUtils::getMapLayerStartColor(null), DefaultsUtils::getMapLayerEndColor(null));
                $order = 1;
                foreach ($limits as $value) {
                    $mapClass = new MapClass();
                    $mapClass->setMapLayer($mapLayer)
                            ->setOrder($order)
                            ->setNumber($value)
                            ->setColor($colors[$order - 1]);

                    $em = $this->doctrine->getManager();
                    $em->persist($mapClass);
                    $em->flush();
                    $order++;
                }


                $mapClass = new MapClass();
                $mapClass->setMapLayer($mapLayer)
                        ->setOrder($order)
                        ->setNumber($value)
                        ->setColor($colors[$order - 1]);

                $em = $this->doctrine->getManager();
                $em->persist($mapClass);
                $em->flush();
                $order++;
            }
        }
    }

    // SEE http://stackoverflow.com/questions/3439792/deep-copy-of-doctrine-record
    public function duplicateMap(\R3gis\AppBundle\Entity\User $user, $sourceHash, $temporary) {
        $em = $this->doctrine->getManager();

        $newHash = md5(uniqid());
        $sourceMap = $this->doctrine->getRepository('R3gisAppBundle:Map')->findOneByHash($sourceHash);
        if (empty($sourceMap)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }

 
        // Duplicate map
        $newMap = clone $sourceMap;
        $newMap->setHash($newHash)
                ->setUser($user)
                ->setTemporary($temporary);
        $em->persist($newMap);

        // Duplicate layers
        $mapLayers = $this->doctrine->getRepository('R3gisAppBundle:MapLayer')->findBy(array('map' => $sourceMap), array('order' => 'ASC'));
        foreach ($mapLayers as $mapLayer) {
            if (!$temporary) {
                // Duplicate table (index, ecc
                
                
                /*
                 * 
                 * $destTable = 'userdata_' . date('Ymd') . '_' . md5(microtime(true) + rand(0, 65535));
        $sql = "CREATE TABLE {$destSchema}.{$destTable} AS {$copySql}";
        $db->exec($sql);

        $userId = $this->map->getUser()->getId();
        $sql = "COMMENT ON TABLE {$destSchema}.{$destTable} IS '" . date('Y-m-d H:i:s') . " User #{$userId}'";
        $db->exec($sql);

        // Update fillfactor
        $sql = "ALTER TABLE {$destSchema}.{$destTable} SET (FILLFACTOR=100)";
        $db->exec($sql);

        // Add PK
        $sql = "ALTER TABLE {$destSchema}.{$destTable} ALTER COLUMN gid SET NOT NULL";
        $db->exec($sql);
        $sql = "ALTER TABLE {$destSchema}.{$destTable} ADD CONSTRAINT {$destTable}_pkey PRIMARY KEY (gid) WITH (FILLFACTOR=100)";
        $db->exec($sql);

        if ($needSpatialColumn) {
            // Add FK to area (partition table)
            $betterTypeLower = strtolower($betterType);
            $sql = "ALTER TABLE {$destSchema}.{$destTable} ADD CONSTRAINT {$destTable}_fk FOREIGN KEY (ar_id) REFERENCES {$destSchema}.area_part_{$betterTypeLower}(ar_id) ON DELETE NO ACTION ON UPDATE NO ACTION NOT DEFERRABLE";
            $db->exec($sql);

            // Cluster on PK
            $sql = "CLUSTER {$destSchema}.{$destTable} USING {$destTable}_pkey";
            $db->exec($sql);
        } else {
            $sql = "SELECT populate_geometry_columns('{$destSchema}.{$destTable}'::regclass)";
            $db->exec($sql);

            $sql = "ALTER TABLE {$destSchema}.{$destTable} ADD CONSTRAINT enforce_is_valid_the_geom CHECK (st_isvalid(the_geom))";
            $db->exec($sql);

            // Cluster on spatial data
            $sql = "SELECT true FROM {$destSchema}.{$destTable} WHERE {$geomColumn} IS NULL LIMIT 1";
            $hasNulls = $db->query($sql)->fetchColumn() === true;
            if (!$hasNulls) {
                $sql = "ALTER TABLE {$destSchema}.{$destTable} ALTER COLUMN {$geomColumn} SET NOT NULL";
                $db->exec($sql);
            }
            $sql = "CREATE INDEX {$destTable}_{$geomColumn}_gist ON {$destSchema}.{$destTable} USING gist ({$geomColumn}) WITH (FILLFACTOR=100)";
            $db->exec($sql);
            $sql = "CLUSTER {$destSchema}.{$destTable} USING {$destTable}_{$geomColumn}_gist";
            $db->exec($sql);
        }

        if (!( $data['it_is_shape'] && empty($spatialColumn) )) {
            $areaTypeId = $db->query("SELECT at_id FROM {$destSchema}.area_type WHERE at_code='{$betterType}'")->fetchColumn();
        }
                 */
                
            }
            $newLayer = clone $mapLayer;
            $newLayer->setMap($newMap)
                    ->setTemporary($temporary);
            $em->persist($newLayer);
            $em->flush();
            
            // Duplicate class
            $mapClass = $this->doctrine->getRepository('R3gisAppBundle:MapClass')->findBy(array('mapLayer' => $mapLayer), array('order' => 'ASC'));
            foreach ($mapClass as $class) {
                $newClass = clone $class;
                $newClass->setMapLayer($newLayer);
                $em->persist($newClass);
                $em->flush();
            }
        }

        $em->flush();

        return $newHash;
    }
    
    // SEE http://stackoverflow.com/questions/3439792/deep-copy-of-doctrine-record
    public function replaceLayersFromMap(\R3gis\AppBundle\Entity\User $user, $toHash, $fromHash) {
        $em = $this->doctrine->getManager();

        $toMap = $this->doctrine->getRepository('R3gisAppBundle:Map')->findOneByHash($toHash);
        if (empty($toMap)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }
        $em->persist($toMap); // Refrash timestamp
        
        $fromHash = $this->doctrine->getRepository('R3gisAppBundle:Map')->findOneByHash($fromHash);
        if (empty($fromHash)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }

        // Delete old layer
        $mapLayers = $this->doctrine->getRepository('R3gisAppBundle:MapLayer')->findBy(array('map' => $toMap), array('order' => 'ASC'));
        foreach ($mapLayers as $mapLayer) {
            // echo "[Remove layer " . $mapLayer->getMap()->getId() . "." . $mapLayer->getOrder() . "]\n";
            $em->remove($mapLayer);
        }
        $em->flush();
        
        // Duplicate layers
        $mapLayers = $this->doctrine->getRepository('R3gisAppBundle:MapLayer')->findBy(array('map' => $fromHash), array('order' => 'ASC'));
        $order = 0;
        foreach ($mapLayers as $mapLayer) {
            $order++;
            $newLayer = clone $mapLayer;
            $newLayer->setMap($toMap)
                     ->setTemporary(false)
                     ->setOrder($order);
            $em->persist($newLayer);
            $em->flush();

            // Duplicate class
            $mapClass = $this->doctrine->getRepository('R3gisAppBundle:MapClass')->findBy(array('mapLayer' => $mapLayer), array('order' => 'ASC'));
            foreach ($mapClass as $class) {
                $newClass = clone $class;
                $newClass->setMapLayer($newLayer);
                $em->persist($newClass);
                $em->flush();
            }
        }

        $em->flush();

        return $toHash;
    }

    // Remove all temporary map starting from hash (child), and optionally the given hash-map (even if not temporary)
    public function purgeTemporaryMap(\R3gis\AppBundle\Entity\User $user, $hash, $purgeCurrent) {
        
        $map = $this->doctrine->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
        $db = $this->db;
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }
        $userId = $user->getid();
        $mapId = $map->getId();

        $sql = "SELECT DISTINCT map_id FROM geobi.map_children_of( {$mapId} ) WHERE us_id={$userId} AND map_temporary IS TRUE";
        $mapToRemove = $db->query($sql)->fetchAll(\PDO::FETCH_COLUMN );
        if ($purgeCurrent) {
            $mapToRemove[] = $map->getId();
        }
        if (count($mapToRemove)> 0) {
            $mapToRemoveList = implode(', ', $mapToRemove);
        
            $sql = "SELECT DISTINCT ml_schema, ml_table FROM geobi.map_layer WHERE map_id IN ({$mapToRemoveList})";
            $tableToRemove = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC );
            
            // Check if table is used on other layer. If not remove it
            foreach($tableToRemove as $table) {
                $sql = "SELECT COUNT(*) FROM geobi.map_layer WHERE ml_schema='{$table['ml_schema']}' AND ml_table='{$table['ml_table']}' AND map_id NOT IN ({$mapToRemoveList})";
                if ($db->query($sql)->fetchColumn( ) == 0) {
                    // Table not used. Drop if
                    $sql = "DROP TABLE IF EXISTS {$table['ml_schema']}.{$table['ml_table']}";
                    $db->exec($sql);
                }
            }
            
            // Deattach parent for non temporary map
            $sql = "UPDATE geobi.map SET map_id_parent=NULL WHERE map_id_parent = " . $map->getId();
            $db->exec($sql);
            
            // Remove layer
            $sql = "DELETE FROM geobi.map_layer WHERE map_id IN ( {$mapToRemoveList} )";
            $db->exec($sql);
            
            // Remove maps
            $sql = "DELETE FROM geobi.map WHERE map_id IN ( {$mapToRemoveList} )";
            $db->exec($sql);
        
        }    
        
    }
    
    // Remove all temporary map starting from hash (child), and optionally the given hash-map (even if not temporary)
    public function purgeOldMaps($ttl) {
        $db = $this->db;
        
        $tot = 0;
        $sql = "SELECT DISTINCT m.map_id
                FROM geobi.map m
                INNER JOIN geobi.map c ON c.map_id_parent=m.map_id AND c.map_temporary IS TRUE
                WHERE m.map_temporary IS FALSE
                ORDER BY m.map_id";
        foreach($db->query($sql, \PDO::FETCH_ASSOC) as $row) {
            $sql = "SELECT DISTINCT map_id
                    FROM geobi.map_children_of({$row['map_id']})
                    WHERE map_temporary IS TRUE AND COALESCE(map_mod_date, map_ins_date) + INTERVAL '+{$ttl} seconds' < now()
                    ORDER BY map_id DESC";
            foreach($db->query($sql, \PDO::FETCH_ASSOC) as $row2) {
                // echo "Removing temporary old map {$row2['map_id']}\n";
                $db->beginTransaction();
                $sql = "DELETE FROM geobi.map_layer WHERE map_id={$row2['map_id']}";
                $db->exec($sql);
                $sql = "DELETE FROM geobi.map WHERE map_id={$row2['map_id']}";
                $db->exec($sql);
                //$db->rollback();
                $db->commit();
                $tot++;
            }
        }
        
        $optimizeTables = array('geobi.map', 'geobi.map_layer', 'geobi.map_class');
        foreach($optimizeTables as $table) {
            $sql = "REINDEX TABLE {$table}";
            $db->exec($sql);
            $sql = "VACUUM FULL ANALYZE {$table}";
            $db->exec($sql);
        }    
                

        return $tot;
    }
    
    public function reorderLayers($hash) {
        
        $map = $this->doctrine->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }
        $db = $this->db;
        $mapId = $map->getId();
        
        $db->beginTransaction();
        
        $sql = "UPDATE geobi.map_layer
                SET ml_order = 1000 * ml_order
                WHERE map_id={$mapId}";
        $db->exec($sql);

        $sql = "UPDATE geobi.map_layer
                SET ml_order = new_order
                FROM (
                    SELECT ROW_NUMBER() OVER () AS new_order, ml_id 
                    FROM geobi.map_layer l
                    WHERE map_id={$mapId}
                    ORDER BY ml_order
                ) foo
                WHERE map_layer.ml_id=foo.ml_id";
        $db->exec($sql);        
        $db->commit();
    }
    
    public function swapLayers($hash, $order1, $order2) {
        
        $map = $this->doctrine->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }
        $db = $this->db;
        $em = $this->doctrine->getManager();
        $mapId = $map->getId();
        
        $mapLayer1 = $this->doctrine->getRepository('R3gisAppBundle:MapLayer')->findOneBy(array('map' => $map, 'order'=>$order1), array('order' => 'ASC'));
        if (empty($mapLayer1)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Later 1 not found');
        }
        $mapLayer2 = $this->doctrine->getRepository('R3gisAppBundle:MapLayer')->findOneBy(array('map' => $map, 'order'=>$order2), array('order' => 'ASC'));
        if (empty($mapLayer2)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Later 2 not found');
        }
        $tmpOrder1 = $mapLayer1->getOrder();
        $tmpOrder2 = $mapLayer2->getOrder();
        
        $mapLayer1->setOrder(-1);
        $em->persist($mapLayer1);
        $em->flush();
        
        $mapLayer2->setOrder($tmpOrder1);
        $em->persist($mapLayer2);
        $em->flush();
        
        $mapLayer1->setOrder($tmpOrder2);
        $em->persist($mapLayer1);
        
        $em->flush();
        
        
    }
    
    // Remove all temporary map alder than ttl sec
    //public function purgeAllMap(\R3gis\AppBundle\Entity\User $user, $ttl) {
    //}
}

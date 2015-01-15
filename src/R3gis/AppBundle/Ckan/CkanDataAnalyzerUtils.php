<?php

namespace R3gis\AppBundle\Ckan;

final class CkanDataAnalyzerUtils {

    /**
     * @type \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * 
     *
     */
    public function __construct(\Doctrine\Bundle\DoctrineBundle\Registry $doctrine) {

        $this->em = $doctrine->getManager();
    }

    /**
     * 
     * @param type $tableNameAnalyze the data in table
     */
    public function analyze($fqTableName) {
        $db = $this->em->getConnection();

        $result = array();

        $hasNumericData = false;
        $hasUniqueData = false;
        $hasSpatialData = false;
        $isShapeFile = false;

        $sql = "SELECT COUNT(*) FROM {$fqTableName}";
        $totRecords = $db->query($sql)->fetchColumn();

        $sql = "SELECT * FROM {$fqTableName} LIMIT 1";
        $columns = array_keys($db->query($sql)->fetch());

        // Ricerca per campi numerici
        foreach ($columns as $column) {
            $result[$column] = array('is_primary_key' => false, 'is_unique' => false, 'is_numeric' => false, 'is_spatial' => false);
            if ($column == 'the_geom') {
                $isShapeFile = true;
                $result[$column]['is_spatial'] = true;
                continue;
            }
            if (in_array($column, array('__pk__', 'gid'))) {
                $result[$column]['is_primary_key'] = true;
                $result[$column]['is_unique'] = true;
                continue;
            }

            // verifica se numero (positivo/negativo/intero/reale)
            $sql = "SELECT COUNT(*), field FROM (
                        SELECT SUBSTR(({$column}::TEXT ~* '^(-)?[0-9.]+$')::TEXT, 1, 1) AS field FROM {$fqTableName}) AS foo
                    GROUP BY field";
            $values = array('t' => 0, 'f' => 0, null => 0);
            foreach ($db->query($sql) as $row) {
                $values[$row['field']] = $row['count'];
            }

            // Is numeric if more than 80% of the data is numeric
            $totRowsWithData = $values['t'] + $values['f'];
            if ($totRowsWithData > 0) {
                $delta = ceil($values['t'] / $totRowsWithData * 100);
                $values = $delta >= 80;
            } else {
                $values = 0;
            }
            $result[$column]['is_numeric'] = $values;


            if (!$values['is_numeric']) {
                // Cerca campi univoci da utilizzare come entità spaziali
                $sql = "SELECT COUNT(*)
                        FROM {$fqTableName}
                        GROUP BY {$column}
                        HAVING COUNT(*) > 1";

                $result[$column]['is_unique'] = $db->query($sql)->fetchColumn() === false;

                // Cerca nomi di entità spaziali (solo se non ho doppioni)
                if ($result[$column]['is_unique']) {
                    // SS: Catch language from db
                    foreach (array('it', 'de', 'en') as $lang) {
                        // @TODO: convert accent to normal chars
                        $sql = "WITH 
                                q1 AS (SELECT loc_text
                                       FROM data.area ar
                                       INNER JOIN data.area_type at ON ar.at_id=at.at_id 
                                       INNER JOIN geobi.localization ON ar.ar_name_id=msg_id and lang_id='{$lang}' AND at_code IN ('MUNICIPALITY', 'PROVINCE', 'REGION', 'NATION')),
                                q2 AS (SELECT {$column}
                                       FROM {$fqTableName})
                                SELECT COUNT(*) 
                                FROM q1 
                                INNER JOIN q2 ON UPPER(
                                    regexp_replace(unaccent((regexp_split_to_array({$column}::TEXT, E'(/)'))[1]), '[^a-zA-Z]', '', 'g')) =
                                    UPPER(regexp_replace(unaccent(loc_text), '[^a-zA-Z]', '', 'g'))";
                        // echo "$sql <br><br>\n\n";        
                        $tot = $db->query($sql)->fetchColumn();
                        $delta = ceil($tot / $totRecords * 100);
                        $values = $delta >= 80;
                        $result[$column]['is_spatial'] = $values;
                        // echo "$sql -> spatial: $values <br><br>\n\n";        
                    }
                }
            }
        }

        $info = array('is_shape_file' => $isShapeFile, 'tot_records' => $totRecords, 'has_unique_data' => false, 'has_spatial_data' => false, 'has_numeric_data' => false);

        foreach ($result as $val) {
            if ($val['is_unique']) {
                $info['has_unique_data'] = true;
            }
            if ($val['is_unique'] && $val['is_spatial']) {
                $info['has_spatial_data'] = true;
            }
            if ($val['is_numeric']) {
                $info['has_numeric_data'] = true;
            }
        }
        return array('info' => $info, 'columns_info' => $result);
    }

    public function getBetterGeometryColumnType($fqTableName, $column) {
        $db = $this->em->getConnection();
        
        // Get better match for every language
        $betterLang = null;
        $betterType = null;
        //$langResult = array();
        $lastMax = -1;
        foreach (array('it', 'de', 'en') as $lang) {
            foreach (array('MUNICIPALITY', 'PROVINCE', 'REGION', 'NATION') as $areaType) {
                $sql = "WITH 
                            q1 AS (SELECT loc_text
                                   FROM data.area ar
                                   INNER JOIN data.area_type at ON ar.at_id=at.at_id 
                                   INNER JOIN geobi.localization ON ar.ar_name_id=msg_id and lang_id='{$lang}' AND at_code='{$areaType}'),
                            q2 AS (SELECT {$column}
                                   FROM {$fqTableName})
                            SELECT COUNT(*) 
                            FROM q1 
                            INNER JOIN q2 ON UPPER(
                                regexp_replace(unaccent((regexp_split_to_array({$column}::TEXT, E'(/)'))[1]), '[^a-zA-Z]', '', 'g')) =
                                UPPER(regexp_replace(unaccent(loc_text), '[^a-zA-Z]', '', 'g'))";
                // echo "$sql\n\n";        die();
                $tot = $db->query($sql)->fetchColumn();
                if ($tot > 0 && $tot > $lastMax) {
                    $betterLang = $lang;
                    $betterType = $areaType;
                    $lastMax = $tot;
                }
            }    
        }

        return array( $betterType, $betterLang );
        
    }
    
    

}

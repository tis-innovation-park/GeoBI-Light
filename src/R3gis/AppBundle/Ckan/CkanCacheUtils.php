<?php

namespace R3gis\AppBundle\Ckan;

use Psr\Log\LoggerInterface;

final class CkanCacheUtils {

    /**
     *
     * @var type  @type LoggerInterface
     */
    private $logger;

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

    private function log($level, $message, array $context = array()) {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * set the logger object
     * @param \Psr\Log\LoggerInterface $logger
     * @return \R3Gis\Common\FileImportBundle\Drivers\Csv
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Remove tables in impexp which are not present in geobi.import_tables
     * @TODO: Check invalid tables
     */
    public function purge(array $validPackages = array()) {

        $db = $this->em->getConnection();
        // Remove deleted packages from cache table
        if (count($validPackages) > 0) {
            $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Purge packages");

            $quotedPackages = array();
            foreach ($validPackages as $package) {
                $quotedPackages[] = $db->quote($package);
            }
            $db->exec("DELETE FROM geobi.import_tables 
                       WHERE it_ckan_package NOT IN (" . implode(", ", $quotedPackages) . ")");
        }

        // SS: Remove tables not present in geobi.import_tables
        $tablesToRemove = $db->query("SELECT table_name 
                              FROM information_schema.tables
                              LEFT JOIN geobi.import_tables ON table_schema=it_schema AND table_name=it_table
                              WHERE table_schema='impexp' and table_type='BASE TABLE' AND it_id IS NULL
                              ORDER BY table_name")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tablesToRemove as $table) {
            $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Purge table {$table}");

            // Remove entry from geometry columns
            $sql = "DELETE FROM public.geometry_columns WHERE f_table_schema='impexp' AND f_table_name='{$table}'";
            $db->exec($sql);
            // Remove table
            $sql = "DROP TABLE IF EXISTS impexp.{$table}";
            $db->exec($sql);
        }

        // Remove entry in geobi.import_tables with not related tables
        $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Purge geobi.import_tables");
        $sql = "DELETE FROM geobi.import_tables
                WHERE it_id IN (
                    SELECT it_id
                    FROM geobi.import_tables
                    LEFT JOIN information_schema.tables ON table_schema=it_schema AND table_name=it_table and table_schema='impexp' and table_type='BASE TABLE' 
                    WHERE it_ckan_valid IS TRUE AND table_name IS NULL)";
        $db->exec($sql);
    }

    /**
     * Lock (in transaction) the cKanPackagee/cKanid
     */
    public function lockEntry($ckanPackage, $ckanId) {
        $db = $this->em->getConnection();

        $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Locking on {$ckanPackage}/{$ckanId}");
        $sql = "SELECT *
                FROM geobi.import_tables
                WHERE it_ckan_package=:it_ckan_package AND it_ckan_id=:it_ckan_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('it_ckan_package' => $ckanPackage, 'it_ckan_id' => $ckanId));
    }

    /**
     * Remove tables in impexp which are not present in geobi.import_tables
     * @TODO: Check invalid tables
     */
    public function addEntry(array $masterData, array $detailData) {

        $defMastedOpt = array('table' => null,
            'sheet' => null,
            'ckan_package' => null,
            'ckan_id' => null,
            'ckan_last_modified' => null,
            'is_valid' => false);

        $masterData = array_merge($defMastedOpt, $masterData);
        list($schemaName, $tableName) = explode('.', $masterData['table']);
        $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Add entry for {$masterData['table']}, sheet={$masterData['sheet']}, package={$masterData['ckan_package']}, id={$masterData['ckan_id']}");

        // SS: Use doctrine listtables
        $db = $this->em->getConnection();
        $sql = "INSERT INTO geobi.import_tables (it_schema, it_table, it_sheet, it_ckan_package, it_ckan_id, it_ckan_date, it_ckan_valid, it_is_shape, it_shape_prj_status)
                VALUES (:it_schema, :it_table, :it_sheet, :it_ckan_package, :it_ckan_id, :it_ckan_date, :it_ckan_valid, :it_is_shape, :it_shape_prj_status)";
        $stmt = $db->prepare($sql);
        // print_r($masterData);
        $stmt->execute(array('it_schema' => $schemaName,
            'it_table' => $tableName,
            'it_sheet' => $masterData['sheet'],
            'it_ckan_package' => $masterData['ckan_package'],
            'it_ckan_id' => $masterData['ckan_id'],
            'it_ckan_date' => $masterData['ckan_last_modified'],
            'it_ckan_valid' => $masterData['is_valid'] ? 't' : 'f',
            'it_is_shape' => $masterData['is_shape'] ? 't' : 'f',
            'it_shape_prj_status' => $masterData['shape_prj_status']));
        $itId = $db->lastInsertId('geobi.import_tables_it_id_seq');

        $sql = "INSERT INTO geobi.import_tables_detail (it_id, itd_column, itd_name, itd_unique_data, itd_spatial_data, itd_numeric_data) 
                VALUES (:it_id, :itd_column, :itd_name, :itd_unique_data, :itd_spatial_data, :itd_numeric_data)";
        $stmt = $db->prepare($sql);
        // print_r($detailData);
        foreach ($detailData as $column => $data) {
            $stmt->execute(array('it_id' => $itId,
                'itd_column' => $column,
                'itd_name' => $data['name'],
                'itd_unique_data' => $data['is_unique'] ? 't' : 'f',
                'itd_spatial_data' => $data['is_spatial'] ? 't' : 'f',
                'itd_numeric_data' => $data['is_numeric'] ? 't' : 'f'));
        }

        return $itId;
    }

    public function hasValidEntry($ckanPackage, $ckanId, $lastModifiedData) {
        $db = $this->em->getConnection();

        $lastModifiedData = str_replace('T', ' ', $lastModifiedData);

        $sql = "SELECT it_id, it_ckan_date
                FROM geobi.import_tables it
                WHERE it_ckan_package=:it_ckan_package AND it_ckan_id=:it_ckan_id
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('it_ckan_package' => $ckanPackage, 'it_ckan_id' => $ckanId));
        $present = false;
        $oldCache = false;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($row['it_ckan_date'] <> $lastModifiedData) {
                // Cache present, but old
                $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Old cache present for package={$ckanPackage}, id={$ckanId}");
                $oldCache = true;
            } else {
                // Cache present
                $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Cache present for package={$ckanPackage}, id={$ckanId}");
                $present = true;
            }
        }

        if ($oldCache) {
            $sql = "DELETE FROM geobi.import_tables it
                    WHERE it_ckan_package=:it_ckan_package AND it_ckan_id=:it_ckan_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('it_ckan_package' => $ckanPackage, 'it_ckan_id' => $ckanId));
        }

        return $present;
    }

}

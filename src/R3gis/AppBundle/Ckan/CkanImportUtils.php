<?php

namespace R3gis\AppBundle\Ckan;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use R3Gis\Common\FileImportBundle\Drivers\Csv;
use R3Gis\Common\FileImportBundle\Drivers\Xls;
use R3Gis\Common\FileImportBundle\Drivers\Shp;
use R3gis\AppBundle\Utils\DefaultsUtils;
use R3gis\AppBundle\Ckan\CkanDataAnalyzerUtils;
use R3gis\AppBundle\Entity\Map;

final class CkanImportUtils {

    /**
     * maximum column length
     */
    const MAX_COLUMN_LENGTH = 60;

    /**
     * @type \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * if true, this library reproject the data
     * 
     * @type boolean
     */
    //private $manualReproject;

    /**
     * @type boolean
     */
    private $hasProjectFile;

    /**
     * @type boolean
     */
    private $hasValidProjectFile;

    public function __construct(\Doctrine\Bundle\DoctrineBundle\Registry $doctrine, array $options = array()) {

        $defOpt = array('temp_path' => '/tmp/',
            'database_driver' => null,
            'database_host' => null,
            'database_port' => null,
            'database_name' => null,
            'database_user' => null,
            'database_password' => null);

        $this->doctrine = $doctrine;
        $this->em = $doctrine->getManager();
        $this->db = $this->em->getConnection();

        $this->options = array_merge($defOpt, $options);

        //$this->manualReproject = true;
        $this->hasProjectFile = false;
        $this->hasValidProjectFile = false;
    }

    //public function manualReproject($manualReproject) {
    //    $this->manualReproject = $manualReproject;
    //}

    public function hasProjectFile() {
        return $this->hasProjectFile;
    }

    public function hasValidProjectFile() {
        return $this->hasValidProjectFile;
    }

    private function mapHeaders(array $headers, array $normalizedHeaders) {
        $mappedHeaders = array();
        foreach ($normalizedHeaders as $key => $val) {
            $mappedHeaders[$val] = $headers[$key];
        }
        return $mappedHeaders;
    }

    private function normalizeHeader(array $headers) {
        // Remove last blank lines
        for ($i = count($headers) - 1; $i >= 0; $i--) {
            if (trim($headers[$i]) === '') {
                unset($headers[$i]);
            }
        }

        // Remove space from name
        foreach ($headers as $key => $val) {
            $val = trim($val);
            if ($val == '') {
                $val = 'COLUMN' . ($key + 1);
            }
            $val = preg_replace('/[^A-Za-z0-9]/', '_', $val);
            $val = preg_replace('/_+/', '_', $val);
            $val = strtolower($val);
            if (ctype_digit($val[0])) {
                $val = "_{$val}";
            }
            $name = substr($val, 0, CkanImportUtils::MAX_COLUMN_LENGTH - 1);
            $headers[$key] = $name;
        }

        // Check column name unique
        $colNames = array();
        foreach ($headers as $key => $name) {
            $newName = $name;
            $count = 0;
            while (in_array($newName, $colNames)) {
                $count++;
                $newName = "{$name}_{$count}";
            }
            $headers[$key] = $newName;
            $colNames[] = $newName;
        }
        return $headers;
    }

    private function createTableFromHeaders($fqDestTable, array $headers) {

        $colNames = array();
        $sqlColNames = array();
        foreach ($headers as $name) {
            /* $name = substr($name, 0, 60);
              $newName = $name;
              $count = 0;
              while (in_array($newName, $colNames)) {
              $count++;
              $newName = "{$name}_{$count}";
              }
              $colNames[] = $newName; */
            $sqlColNames[] = "  {$name} TEXT";
        }
        if (count($sqlColNames) == 0) {
            return false;
        }
        list($dummy, $pkName) = explode('.', $fqDestTable);
        $sql = "CREATE TABLE {$fqDestTable} (\n  __pk__ SERIAL," . implode(",\n", $sqlColNames) . ",\n  CONSTRAINT {$pkName}_pkey PRIMARY KEY(__pk__))";

        $this->db->exec($sql);
    }

    private function populateTable($fqDestTable, array $headers, \Iterator $iterator) {

        $insertedRows = 0;
        if (count($headers) > 0) {

            $sql = "INSERT INTO {$fqDestTable} (" . implode(', ', $headers) . ") " .
                    "VALUES (" . implode(', ', array_fill(0, count($headers), '?')) . ")";

            // Use PDO directly for memory and performance issue
            $stmt = $this->db->getWrappedConnection()->prepare($sql);

            $totColumns = count($headers);
            foreach ($iterator as $row) {
                $hasValue = false;
                foreach ($row as $key => $dummy) {
                    $row[$key] = trim($row[$key]);
                    if ($row[$key] == '') {
                        $row[$key] = null;
                    } else {
                        $hasValue = true;
                    }
                }
                if ($hasValue) {
                    $row = array_values($row);
                    $row = array_slice($row, 0, $totColumns);
                    $stmt->execute($row);
                    $insertedRows++;
                }
            }
        }
        return $insertedRows;
    }

    public function importCsvFile($sourceFile, $fqDestTable) {
        $result = array();

        $csv = new Csv($sourceFile, array('delimiter' => ','));
        $headers = $csv->getHeader();

        // Check for CSV separator (, or ; supported)
        if (count($headers) == 1 && strpos($headers[0], ';')) {
            // Separator is , 
            $csv = new Csv($sourceFile, array('delimiter' => ';'));
            $headers = $csv->getHeader();
        }

        $normalizedHeaders = $this->normalizeHeader($headers);

        $sheetInfo = array('file' => $sourceFile,
            'name' => null, // No sheet name
            'table' => $fqDestTable,
            'tot_records' => 0,
            'prj_status' => null,
            'headers' => $this->mapHeaders($headers, $normalizedHeaders));
        $this->createTableFromHeaders($fqDestTable, $normalizedHeaders);
        $totRecords = $this->populateTable($fqDestTable, $normalizedHeaders, $csv->getIterator());
        $sheetInfo['tot_records'] = $totRecords;

        return array($sheetInfo);
    }

    public function importShpFile($sourceFile, $fqDestTable) {
        $result = array();

        $db = $this->db;

        $shp = new Shp($sourceFile);
        $shp->setTempDir($this->options['temp_path'])
                ->setDatabaseDriver($this->options['database_driver'])
                ->setDatabaseHost($this->options['database_host'])
                ->setDatabasePort($this->options['database_port'])
                ->setDatabaseName($this->options['database_name'])
                ->setDatabaseUser($this->options['database_user'])
                ->setDatabasePassword($this->options['database_password']);
        // $shp->setSrid(4326);
        $dmpFile = null;
        // Try to impost shape with default encoding, utf8, latin1
        foreach (array(null, 'UTF-8', 'LATIN1') as $encoding) {
            $shp->setEncoding($encoding);
            try {
                $dmpFile = $shp->shp2sql($fqDestTable);
                $shp->sql2pgsql($dmpFile);
                break;
            } catch (\Exception $e) {
                // Show nothing: Simply try with another encoding
            }
        }

        if (empty($dmpFile)) {
            throw new \Exception("Can't import \"{$sourceFile}\"");
        }
        // Change pk name 
        $fs = new Filesystem();
        $fs->remove($dmpFile);

        $prjFile = substr($sourceFile, 0, -4) . '.prj';

        $this->hasProjectFile = $this->hasValidProjectFile = $fs->exists($prjFile);
        if ($this->hasProjectFile) {
            $line = explode("\n", file_get_contents($prjFile));
            $prjDef = trim($line[0]);
            $sql = "SELECT srid
                    FROM public.spatial_ref_sys
                    WHERE srtext=:srtext";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('srtext' => $prjDef));
            $srid = $stmt->fetchColumn();
            if (empty($srid)) {
                $this->hasValidProjectFile = false;
            }
        }

        $totRecords = $this->db->query("SELECT COUNT(*) FROM {$fqDestTable}")->fetchColumn();

        $headers = array_keys($this->db->query("SELECT * FROM {$fqDestTable} LIMIT 1")->fetch(\PDO::FETCH_ASSOC));
        $normalizedHeaders = $this->normalizeHeader($headers);
        // More normalization
        foreach ($normalizedHeaders as $key => $val) {
            if (!in_array($val, array('gid', 'the_geom'))) {
                $normalizedHeaders[$key] = UCWords(trim(str_replace('_', ' ', $val)));
            }
        }

        // M=missing, V=Valid, I=Invalid
        $projStatus = 'M';  // SS: CONST|MISISNG
        if ($this->hasProjectFile) {
            if ($this->hasValidProjectFile) {
                $projStatus = 'V';
            } else {
                $projStatus = 'I';
            }
        }
        $sheetInfo = array('file' => $sourceFile,
            'name' => null, // No sheet name
            'table' => $fqDestTable,
            'tot_records' => $totRecords,
            'prj_status' => $projStatus,
            'headers' => $this->mapHeaders($normalizedHeaders, $headers));
        // TODO: Repair geometries!
        return array($sheetInfo);
    }

    public function importXlsFile($sourceFile, $fqDestTable) {
        $result = array();

        $xls = new Xls($sourceFile);
        $xls->load();
        $sheets = $xls->getAllSheetName();

        for ($sheetNo = 0; $sheetNo < $xls->getSheetCount(); $sheetNo++) {
            $fqDestTableWithSheet = "{$fqDestTable}_{$sheetNo}";

            $sheetInfo = array('file' => $sourceFile,
                'name' => $sheets[$sheetNo],
                'table' => $fqDestTableWithSheet,
                'tot_records' => 0,
                'prj_status' => null,
                'headers' => array());

            $xls->setActiveSheetIndex($sheetNo);
            $destTableSheet = "{$fqDestTable}_{$sheetNo}";

            $headers = $xls->getHeader();
            $normalizedHeaders = $this->normalizeHeader($headers);
            $sheetInfo['headers'] = $this->mapHeaders($headers, $normalizedHeaders);

            $this->createTableFromHeaders($fqDestTableWithSheet, $normalizedHeaders);
            $totRecords = $this->populateTable($fqDestTableWithSheet, $normalizedHeaders, $xls->getIterator());
            $sheetInfo['tot_records'] = $totRecords;

            $result[] = $sheetInfo;
        }
        unset($xls);
        return $result;
    }

    // Try to convert zip filename to utf-8 form CP437/CP850
    // see https://bugs.php.net/bug.php?id=65815
    private function purgeZipFilename($filename) {

        if ($filename === mb_convert_encoding(mb_convert_encoding($filename, "UTF-32", "UTF-8"), "UTF-8", "UTF-32")) {
            // Npothing to change
            $result = $filename;
        } else {
            // otherwise we should use 
            $result = mb_convert_encoding($filename, 'UTF-8', 'CP850');
        }
        return $result;
    }

    // SS: normalized extesion needed (see ckanUtils::extractFormat)
    public function importZipFile($sourceFile, $fqDestTable) {
        $validExt = array('.csv' => array(), '.xls' => array(), '.xlsx' => array(), '.shp' => array('.shx', '.dbf', '.prj', '.cpg'));

        $files = array();
        $filesMapCaseInsensitive = array(); // For shape names

        $fs = new Filesystem();
        if (!$fs->exists($sourceFile)) {
            throw new \Exception("File \"{$sourceFile}\" not found");
        }
        $zip = new \ZipArchive();
        if ($zip->open($sourceFile) !== true) {
            throw new \Exception("Error open file \"{$sourceFile}\"");
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            // Exclude directory
            if (substr($stat['name'], -1) <> '/') {
                $stat['ext'] = strtolower(strrchr($stat['name'], '.'));
                $stat['purged_name'] = $this->purgeZipFilename($stat['name']);
                $files[$stat['name']] = $stat;
            }
        }
        $zip->close();
        unset($zip);

        $tableSeq = 1;
        $result = array();
        foreach ($files as $file) {
            if (array_key_exists($file['ext'], $validExt)) {
                $filesToExtract = array($file['name']);
                // Search for related files
                foreach ($validExt[$file['ext']] as $relatedExt) {
                    $relatedFilename = substr($file['name'], 0, -strlen($file['ext'])) . $relatedExt;
                    if (array_key_exists($relatedFilename, $files)) {
                        $filesToExtract[] = $relatedFilename;
                    }
                }

                // Extract files
                $tmpFileName = $this->options['temp_path'] . md5(uniqid());
                $extractedFiles = array();  // Remove after import
                foreach ($filesToExtract as $filename) {
                    $ext = strtolower(strrchr($filename, '.'));
                    $filenameToExtract = "{$tmpFileName}{$ext}";
                    // echo "zip://{$sourceFile}#{$filename}=>{$filenameToExtract} <br>\n";
                    if (!copy("zip://{$sourceFile}#{$filename}", $filenameToExtract)) {
                        throw new Exception("Error extracting from {$sourceFile} the file {$filename} to {$filenameToExtract}");
                    }
                    $extractedFiles[] = $filenameToExtract;
                }

                // Import files
                $lastResult = $this->importFile("{$tmpFileName}{$file['ext']}", "{$fqDestTable}_{$tableSeq}");
                foreach ($lastResult as $key => $dummy) {
                    $name = basename($file['purged_name']);
                    $ext = strtolower(strrchr($name, '.'));
                    $name = substr($name, 0, -strlen($ext));
                    $name = str_replace('_', ' ', $name);

                    if (empty($lastResult[$key]['name'])) {
                        $lastResult[$key]['name'] = $name;
                    } else {
                        $lastResult[$key]['name'] = "{$lastResult[$key]['name']} - {$name}";
                    }
                }
                $tableSeq++;

                // Cleanup
                $fs->remove($extractedFiles);

                $result[] = $lastResult[0];
            }
        }
        return $result;
    }

    // Converte tutti campi in INTEGER/DOUBLE/VARCHAR
    private function optimizeAllTable(array $result) {
        $db = $this->db;

        foreach ($result as $table) {
            foreach ($table['headers'] as $column => $dummy) {
                if (in_array($column, array('gid', '__pk__', 'the_geom'))) {
                    continue;
                }
                $altered = false;

                // Check for float
                $sql = "SELECT false
                        FROM {$table['table']} 
                        WHERE (LENGTH(TRIM({$column}::TEXT)) > 18) OR (NOT TRIM({$column}::TEXT) ~* '^(-)?[0-9]*\.{1}[0-9]+$')
                        LIMIT 1";
                if (!$altered && $db->query("SELECT COUNT(*) FROM ({$sql}) foo")->fetchColumn() == 0) {
                    $sql = "ALTER TABLE {$table['table']} ALTER COLUMN {$column} TYPE DOUBLE PRECISION USING(TRIM({$column}::TEXT)::DOUBLE PRECISION)";
                    $db->exec($sql);
                    $altered = true;
                }

                // Check for integer
                $sql = "SELECT false
                        FROM {$table['table']} 
                        WHERE (LENGTH(TRIM({$column}::TEXT)) > 9) OR (NOT {$column}::TEXT ~* '^(-)?[0-9]+$')
                        LIMIT 1";
                if (!$altered && $db->query("SELECT COUNT(*) FROM ({$sql}) foo")->fetchColumn() == 0) {
                    $sql = "ALTER TABLE {$table['table']} ALTER COLUMN {$column} TYPE INTEGER USING(TRIM({$column}::TEXT)::INTEGER)";
                    $db->exec($sql);
                    $altered = true;
                }

                // Check for bigint
                $sql = "SELECT false
                        FROM {$table['table']} 
                        WHERE (LENGTH(TRIM({$column}::TEXT)) > 18) OR (NOT {$column}::TEXT ~* '^(-)?[0-9]+$')
                        LIMIT 1";
                if (!$altered && $db->query("SELECT COUNT(*) FROM ({$sql}) foo")->fetchColumn() == 0) {
                    $sql = "ALTER TABLE {$table['table']} ALTER COLUMN {$column} TYPE BIGINT USING(TRIM({$column}::TEXT)::BIGINT)";
                    $db->exec($sql);
                    $altered = true;
                }

                if (!$altered) {
                    $sql = "ALTER TABLE {$table['table']} ALTER COLUMN {$column} TYPE VARCHAR";
                    $db->exec($sql);
                }
            }
        }
    }

    public function importFile($sourceFile, $fqDestTable) {
        $ext = strtolower(strrchr($sourceFile, '.'));

        switch ($ext) {
            case '.csv':
                $result = $this->importCsvFile($sourceFile, $fqDestTable);
                $this->optimizeAllTable($result);
                break;

            case '.shp':
                $result = $this->importShpFile($sourceFile, $fqDestTable);
                $this->optimizeAllTable($result);
                break;

            case '.xls':
                $result = $this->importXlsFile($sourceFile, $fqDestTable);
                $this->optimizeAllTable($result);
                break;

            case '.zip':
                $result = $this->importZipFile($sourceFile, $fqDestTable);
                break;

            default:
                throw new \Exception("Unknown format \"{$ext}\"");
        }

        return $result;
    }

}

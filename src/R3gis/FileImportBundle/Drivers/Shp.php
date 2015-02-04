<?php

namespace R3gis\FileImportBundle\Drivers;

use Psr\Log\LoggerInterface;
use R3gis\FileImportBundle\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Import driver for shape files
 *
 * @category  Database import
 * @package   R3gis\FileImportBundle
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 * 
 */
class Shp implements Driver {

    /**
     * Import options for shape file
     */
    private $options = array(
        'create_table' => true,
        'load_data' => true,
        'create_index' => true,
        'srid' => null,
        'geometry_column' => 'the_geom',
        'dump_format' => 'binary',
        'case_sensitive' => false,
        'force_int4' => false,
        'simple_geometry' => false,
        'encoding' => null,
        'shp2pgsql_cmd' => 'shp2pgsql',
        'psql_cmd' => 'psql',
        'temp_dir' => '/tmp/'
    );

    /**
     *
     * @var type  @type LoggerInterface
     */
    private $logger;
    private $filename;
    protected $codepageConversionTable = array(87 => 'ISO-8859-1', 27 => 'ISO-8859-1', 4 => 'ISO-8859-1');   // Code page conversion table (codepage=>charset)  // 4=>Macintosh code page. Conversion

    public function __construct($filename, array $options = array()) {

        $this->filename = $filename;

        $this->options = array_merge($this->options, $options);

        $this->setDatabaseDriver('pdo_pgsql')
                ->setDatabaseHost('127.0.0.1')
                ->setDatabasePort(5432);
    }

    /**
     * get supported file extensions for this import
     * @return array
     */
    public function getExtensions() {
        return array(
            'name' => 'ESRI Shape file',
            'extensions' => array('shp', 'dbf', 'shx')
        );
    }

    /**
     * 
     */
    private function log($level, $message, array $context = array()) {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * set the logger object
     * @param \Psr\Log\LoggerInterface $logger
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
        return $this;
    }

    /**
     * return the logger object
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger() {
        return $this->logger;
    }

    /**
     * set the database driver to use. Only postgres (with postgis) supported
     * @param string $databaseDriver Driver name (pdo_pgsql)
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setDatabaseDriver($databaseDriver) {
        if ($databaseDriver != 'pdo_pgsql') {
            throw new \Exception("Invalid database driver \"{$databaseDriver}\"");
        }
        $this->databaseDriver = $databaseDriver;
        return $this;
    }

    /**
     * set the database host to use
     * @param string $databaseHost
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setDatabaseHost($databaseHost) {
        $this->databaseHost = $databaseHost;
        return $this;
    }

    /**
     * set the database port to use
     * @param string $databasePort
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setDatabasePort($databasePort) {
        $this->databasePort = $databasePort;
        return $this;
    }

    /**
     * set the database name to use
     * @param string $databaseName
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setDatabaseName($databaseName) {
        $this->databaseName = $databaseName;
        return $this;
    }

    /**
     * set the database user
     * @param string $databaseUser
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setDatabaseUser($databaseUser) {
        $this->databaseUser = $databaseUser;
        return $this;
    }

    /**
     * set the database password
     * @param string $databasePassword
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setDatabasePassword($databasePassword) {
        $this->databasePassword = $databasePassword;
        return $this;
    }

    /**
     * set the srid
     * @param string $srid
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setSrid($srid) {
        $this->options['srid'] = $srid;
        return $this;
    }
    
    /**
     * set the encoding
     * @param string $encoding
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setEncoding($encoding) {
        $this->options['encoding'] = $encoding;
        return $this;
    }

    /**
     * set the database password
     * @param string $databasePassword
     * @return \R3gis\FileImportBundle\Drivers\Shp
     */
    public function setTempDir($dir) {
        $this->options['temp_dir'] = $dir;
        return $this;
    }

    public function shp2sql($destinationTable) {
        
        $this->log(\Psr\Log\LogLevel::DEBUG, "Dumping shapefile \"{$this->filename}\" into \"{$this->options['temp_dir']}\"");

        $fs = new Filesystem();
        if (!$fs->exists($this->options['temp_dir']) || !is_writable($this->options['temp_dir'])) {
            throw new \Exception("Output directory \"{$this->options['temp_dir']}\" invalid or not writable");
        }

        $cmdOpt = array();

        /* dump type */
        if ($this->options['create_table'] == true && $this->options['load_data'] == true) {
            $cmdOpt[] = '-c';
        } else if ($this->options['create_table'] == true && $this->options['load_data'] == false) {
            $cmdOpt[] = '-p';
        } else if ($this->options['create_table'] == false && $this->options['load_data'] == true) {
            $cmdOpt[] = '-a';
        } else {
            throw new \Exception('Invalid options: create and/or data must be true');
        }

        /* srid */
        if (!empty($this->options['srid'])) {
            $cmdOpt[] = "-s {$this->options['srid']}";
        }

        /* Geometry column */
        $cmdOpt[] = "-g {$this->options['geometry_column']}";

        /* Dump format */
        if (strtolower($this->options['dump_format']) == 'binary') {
            $cmdOpt[] = '-D';
        }

        /* Case sensitive */
        if ($this->options['case_sensitive'] == true) {
            $cmdOpt[] = '-k';
        }

        /* Force int 4 */
        if ($this->options['force_int4'] == true) {
            $cmdOpt[] = '-i';
        }

        /* Force simple geometry */
        if ($this->options['simple_geometry'] == true) {
            $cmdOpt[] = '-S';
        }

        /* Create index */
        if ($this->options['create_index'] == true) {
            $cmdOpt[] = '-I';
        }

        /* Encoding */
        $encoding = $this->options['encoding'];
        if (empty($encoding)) {
            // Get the encoding from the dbfor cpg
            // filename may be given with extension. Check if this is true
            // and construct path for dbf file correctly

            $this->log(\Psr\Log\LogLevel::DEBUG, "Extracting enconding from DBF file");

            $filenameNoExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $this->filename);
            $dbfFilename = "{$filenameNoExt}.dbf";
            $dbfFilenameUpper = "{$filenameNoExt}.DBF";
            $cpgFilename = "{$filenameNoExt}.cpg";
            $cpgFilenameUpper = "{$filenameNoExt}.CPG";

            $hDbfFile = false;
            foreach (array($dbfFilename, $dbfFilenameUpper) as $dbfFilename) {
                if ($fs->exists($dbfFilename)) {
                    if (($hDbfFile = fopen($dbfFilename, "rb")) === false) {
                        throw new \Exception("Cant open dbf file \"{$testfile}\"");
                    }
                    break;
                }
            }
            if ($hDbfFile === false) {
                throw new \Exception("Can not find dbf file");
            }
            if (fseek($hDbfFile, 29) !== 0) {
                throw new \Exception("Can't seek to position 29 in file [$testfile]");
            }
            $codepage = ord(fread($hDbfFile, 1));
            fclose($hDbfFile);
            if ($codepage > 0) {
                if (array_key_exists($codepage, $this->codepageConversionTable)) {
                    $encoding = $this->codepageConversionTable[$codepage];
                } else {
                    $encoding = 'UTF-8';
                }
                $this->log(\Psr\Log\LogLevel::DEBUG, "DBF encoding: \"{$encoding}\"");
            } else {
                foreach (array($cpgFilename, $cpgFilenameUpper) as $cpgFilename) {
                    if ($fs->exists($cpgFilename)) {
                        $this->log(\Psr\Log\LogLevel::DEBUG, "Extracting econding from CPG file");
                        $lines = file($cpgFilename, FILE_IGNORE_NEW_LINES);
                        if ($lines === false || count($lines) == 0) {
                            throw new Exception("Can't open cpg \"{$cpgFilename}\"");
                        }
                        $encoding = $lines[0];
                        $this->log(\Psr\Log\LogLevel::DEBUG, "DBF encoding: \"{$encoding}\"");
                        break;
                    }
                }
            }
        }

        if (!empty($encoding)) {
            $cmdOpt[] = "-W {$encoding}";
        }

        $outFileNoExt = $this->options['temp_dir'] . date('YmdHis') . '-' . md5(microtime(true) + rand(0, 65535));
        $outSqlFile = "{$outFileNoExt}.sql";
        $outErrFile = "{$outFileNoExt}.err";

        $cmd = escapeshellarg($this->options['shp2pgsql_cmd']) . " " . implode(' ', $cmdOpt) . " " .
                escapeshellarg($this->filename) . " " . escapeshellarg($destinationTable) . " > " .
                escapeshellarg($outSqlFile) . " 2> " . escapeshellarg($outErrFile);

        $this->log(\Psr\Log\LogLevel::DEBUG, "Executing \"{$cmd}\"");
        $startTime = microtime(true);

        exec($cmd, $shp2pgsqlOutput, $retVal);

        $totTime = $start = microtime(true) - $startTime;
        $this->log(\Psr\Log\LogLevel::DEBUG, sprintf("Execution time: %.1f sec", $totTime));

        if ($fs->exists($outErrFile)) {
            foreach (file($outErrFile, FILE_IGNORE_NEW_LINES) as $line) {
                $this->log(\Psr\Log\LogLevel::NOTICE, "shp2pgsql: {$line}");
            }
            $fs->remove($outErrFile);
        }
        if ($retVal <> 0) {
            if ($fs->exists($outSqlFile)) {
                $fs->remove($outSqlFile);
            }
            $errorText = "shp2pgsql return {$retVal}\nCommand was:\n{$cmd}";
            $this->log(\Psr\Log\LogLevel::DEBUG, $errorText);
            throw new \Exception($errorText);
        }

        return $outSqlFile;
    }

    /**
     * Import the dump file to postgres
     *
     * @param string         file name to import (sql file)
     * @return array         options
     * @access protected
     */
    public function sql2pgsql($sqlFilename) {

        $this->log(\Psr\Log\LogLevel::DEBUG, "Importing sql file \"{$sqlFilename}\" into {$this->databaseName} on {$this->databaseHost}:{$this->databasePort}");

        $fs = new Filesystem();

        $cmdOpt = array();
        $cmdOpt[] = "-h {$this->databaseHost}";
        if(!empty($this->databasePort)) {
            $cmdOpt[] = "-p {$this->databasePort}";
        }    
        $cmdOpt[] = "-d {$this->databaseName}";
        $cmdOpt[] = "-f " . escapeshellarg($sqlFilename);
        $cmdOpt[] = "--set=ON_ERROR_STOP=1";  // force psql to return error (on error)

        $outFileNoExt = $this->options['temp_dir'] . date('YmdHis') . '-' . md5(microtime(true) + rand(0, 65535));
        $outOutFileName = "{$outFileNoExt}.out";
        $outErrFileName = "{$outFileNoExt}.err";

        putenv("PGUSER={$this->databaseUser}");
        putenv("PGPASSWORD={$this->databasePassword}");
        // putenv("PGOPTIONS=-c log_statement=none");  <- PROBLEM WITH NON PRIVILEGED USER

        $cmd = escapeshellarg($this->options['psql_cmd']) . " " . implode(' ', $cmdOpt) . " > " .
                escapeshellarg($outOutFileName) . " 2> " . escapeshellarg($outErrFileName);

        $this->log(\Psr\Log\LogLevel::DEBUG, "Executing \"{$cmd}\"");
        $startTime = microtime(true);

        exec($cmd, $outOutFile, $retVal);
        
        $totTime = $start = microtime(true) - $startTime;
        $this->log(\Psr\Log\LogLevel::DEBUG, sprintf("Execution time: %.1f sec", $totTime));

        if ($fs->exists($outErrFileName)) {
            foreach (file($outErrFileName, FILE_IGNORE_NEW_LINES) as $line) {
                $this->log(\Psr\Log\LogLevel::NOTICE, "psql: {$line}");
            }
            $fs->remove($outErrFileName);
        }
        if ($retVal <> 0) {
            $errorText = "psql return {$retVal}\nCommand was:\n{$cmd}";
            $this->log(\Psr\Log\LogLevel::DEBUG, $errorText);
            throw new \Exception($errorText);
        }
        $fs->remove($outOutFileName);
    }

}

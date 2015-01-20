<?php

namespace R3gis\FileImportBundle\Drivers;

use Psr\Log\LoggerInterface;
use R3gis\FileImportBundle\Driver;

/**
 * Import driver for csv files
 *
 * @category  Database import
 * @package   R3gis\FileImportBundle
 * @author    Daniel Degasperi <daniel.degasperi@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 * 
 * @todo use logger->debug instead of logger->info??
 * @todo use stopwatch instead of microtime??
 */
class Csv implements Driver {

    /**
     * Import options for CSV
     */
    private $options = array(
        'read_buffer' => 8192, // read buffer size
        'delimiter' => ';', // field separator char
        'enclosure' => '"', // quoted char
        'escape' => "\\",
        'trim' => false,
        'first_line_header' => true, // if true the first line is the header
        'encoding' => 'ISO-8859-15',
    );

    /*
     * The csv objcet 
     */
    protected $csv = null;

    /*
     * Table column definition (of database)
     */
    protected $colDefs = null;

    /*
     * MDB2 link
     */
    protected $db = null;

    /*
     * Temporary table name
     */
    protected $temporaryTableName = null;
    private $logger;

    /**
     * Constructor for csv import driver
     * @param \Doctrine\DBAL\Connection $connection
     */
    public function __construct($filename, array $options = array()) {
        $this->filename = $filename;

        $this->options = array_merge($this->options, $options);

        // check if iterator has to skip the header
        if ($this->options['first_line_header']) {
            $this->options['skip_lines'] = 1;
        } else {
            $this->options['skip_lines'] = 0;
        }
    }

    /**
     * return a iterator object to cycle each line from csv file
     * @return \R3gis\FileImportBundle\Drivers\CsvIterator
     */
    public function getIterator() {
        return new CsvIterator($this->filename, $this->options);
    }

    /**
     * get header information from first line in csv file
     * @return array
     * @throws \Exception
     */
    public function getHeader() {
        // check if first line is the header
        if (!$this->options['first_line_header']) {
            throw new \Exception("set first_line_header option true");
        }

        // open csv
        $csv = fopen($this->filename, 'r');
        if (!$csv) {
            throw new \Exception(sprintf("Can't open file \"%s\"", $this->filename));
        }

        // read first line of csv file
        $header = fgetcsv($csv, $this->options['read_buffer'], $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);

        // close file
        fclose($csv);

        // trim header names
        if ($this->options['trim']) {
            foreach ($header as $key => $value) {
                if (is_string($value)) {
                    $header[$key] = trim($value);
                }
            }
        }

        return $header;
    }

    /**
     * set the logger object
     * @param \Psr\Log\LoggerInterface $logger
     * @return \R3gis\FileImportBundle\Drivers\Csv
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
     * get supported file extensions for this import
     * @return array
     */
    public function getExtensions() {
        return array(
            'name' => 'Comma separated file',
            'extensions' => array('csv')
        );
    }

}

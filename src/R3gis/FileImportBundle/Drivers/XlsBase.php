<?php

namespace R3gis\FileImportBundle\Drivers;

use Psr\Log\LoggerInterface;
use R3gis\FileImportBundle\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;;

/**
 * Import driver for xls files
 *
 * @category  Database import
 * @package   R3gis\FileImportBundle
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 * 
 * @todo use logger->debug instead of logger->info??
 * @todo use stopwatch instead of microtime??
 */
abstract class XlsBase  {
    
    /**
     * Import options for XLS
     */
    private $options = array(
        'first_line_header' => true, // if true the first line is the header
        'case_sensitive' => false
    );

    /*
     * The xls objcet 
     */
    protected $xls = null;
    
    protected $spreadsheetReader = null;
    
    protected $filename;
    
    /**
     * Constructor for xls import driver
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
        
        $fs = new Filesystem();
        if (!$fs->exists($filename)) {
            throw new \Exception(sprintf("Can't open file \"%s\"", $this->filename));
        } 
        
    }
    
    /**
     * return a iterator object to cycle each line from xls file
     * @return \R3gis\FileImportBundle\Drivers\XlsIterator
     */
    public function getIterator() {
        $options = array_merge($this->options, array('headers'=>$this->getNormalizedHeader()));
        return new XlsIterator($this->xls, $options);
    }
    
    /**
     * get header information from first line in xls file
     * @return array
     * @throws \Exception
     */
    public function getHeader() {
        $sheet = $this->xls->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn);

        $headers = array();
        for ($col = 0; $col <= $highestColumnIndex; ++$col) {  
            $headers[] = $sheet->getCellByColumnAndRow($col, 1)->getValue();  
        }
        // SS: Todo check for unique column name
        return $headers;
    }
    
    public function getNormalizedHeader() {
        
        $headers = $this->getHeader();
        // Remove last blank lines
        for($i = count($headers) - 1; $i >= 0; $i--) {
            if (trim($headers[$i]) === '') {
                unset($headers[$i]);
            }    
        }
        // Remove space from name
        foreach($headers as $key=>$val) {
            $val = trim($val);
            $val = preg_replace('/-/', '_', $val);
            $val = preg_replace('/[^A-Za-z0-9-]/', '_', $val);
            $val = preg_replace('/_+/', '_', $val);
            if (!$this->options['case_sensitive']) {
                $val = strtolower($val);
            }
            $headers[$key] = $val;
        }
        
        return $headers;
    }
    
    /**
     * set the logger object
     * @param \Psr\Log\LoggerInterface $logger
     * @return \R3gis\FileImportBundle\Drivers\Xls
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
    
    public function getSheetCount() {
        return $this->xls->getSheetCount();
    }
    
    public function getAllSheetName() {
        return $this->xls->getSheetNames(); 
    }
    
    public function setActiveSheetIndex($sheetNo) {
        $this->xls->setActiveSheetIndex($sheetNo);
        return $this;
    }
    
}

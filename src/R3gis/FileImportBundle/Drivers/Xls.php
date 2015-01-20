<?php

namespace R3gis\FileImportBundle\Drivers;

use Psr\Log\LoggerInterface;
use R3gis\FileImportBundle\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;



/**
 * Import driver for xls files
 *
 * @category  Database import
 * @package   W:\geobi\geobi\src\R3gis\AppBundle
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 * 
 * @todo use logger->debug instead of logger->info??
 * @todo use stopwatch instead of microtime??
 */
class Xls extends XlsBase implements Driver {
    
    public function load() {
        $inputFileType = \PHPExcel_IOFactory::identify($this->filename);  
        $this->spreadsheetReader = \PHPExcel_IOFactory::createReader($inputFileType);  
        $this->spreadsheetReader->setReadDataOnly(true);
        $this->xls = $this->spreadsheetReader->load($this->filename); 
    }
    
    /**
     * get supported file extensions for this import
     * @return array
     */
    public function getExtensions() {
        return array(
            'name' => 'Comma separated file',
            'extensions' => array('xls')
        );
    }
}

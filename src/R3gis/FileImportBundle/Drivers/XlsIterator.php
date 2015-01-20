<?php

namespace R3gis\FileImportBundle\Drivers;

/**
 * iterator for xls files
 *
 * @category  Database import
 * @package   R3gis\FileImportBundle
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
class XlsIterator implements \Iterator {
    
    /**
     * filepoint of the xls file
     * @var filepointer
     */
    private $xls;
    
    /**
     * default options
     * @var array
     */
    private $options = array(
        //'read_buffer' => 8192, // read buffer size
        //'delimiter' => ';', // field separator char
        //'enclosure' => '"', // quoted char
        //'escape' => "\\",
        //'trim' => false,
        //'skip_lines' => 1,
        //'encoding' => 'ISO-8859-15',
    );
    
    /**
     * current line
     * @var array
     */
    //private $line;
    
    /**
     * current line number (excluding skipped lines)
     * @var integer
     */
    //private $lineNumber = 0;
    
    /**
     * true if reached end of file
     * @var boolean
     */
    //private $fileEnd;
    
    private $currentRow;
    
    public function __construct($xls, array $options = array()) {
        $this->xls = $xls;
        
        $this->options = array_merge($this->options, $options);
        
        $sheet = $this->xls->getActiveSheet();
        $this->highestRow = $sheet->getHighestRow();
        
        //print_r($this->options);
    }
    
    /*public function __destruct() {
        if ($this->xls) {
            fclose($this->xls);
        }
    }*/
    
    /*private function readLine() {
        // check if reached end of line
        if (feof($this->xls)) {
            $this->fileEnd = true;
            return null;
        }
        
        // read line of xls file (do not use fgetxlsxls, because 'locale setting is taken into account by this function')
        $lineString = fgets($this->xls, $this->options['read_buffer']);
        if ($lineString === false) {
            // there could be an error, because of a blank line at end of file
            if (feof($this->xls)) {
                $this->fileEnd = true;
                return null;
            }
            
            throw new \Exception("could not read line ".($this->lineNumber + $this->options['skip_lines']));
        }
        
        // convert linestring into utf-8 encoding
        if (strtoupper($this->options['encoding'])  !== 'UTF-8' && strtoupper($this->options['encoding'])  !== 'UTF8') {
            $lineString = iconv($this->options['encoding'], 'UTF-8', $lineString);
        }
        
        // parse xls linestring
        $line = str_getxls($lineString, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
        if (is_null($line) || $line === false) {
            throw new \Exception("could not parse line ".($this->lineNumber + $this->options['skip_lines']));
        }
        
        // trim string values
        if ($this->options['trim']) {
            foreach($line as $key => $value) {
                if (is_string($value)) {
                    $line[$key] = trim($value);
                }
            }
        }
        
        return $line;
    }*/
    
    public function current() {
        $sheet = $this->xls->getActiveSheet();
        for($col = 0; $col < count($this->options['headers']); $col++) {
            $headerName = $this->options['headers'][$col];
            $row[$headerName] = $sheet->getCellByColumnAndRow($col, $this->currentRow)->getValue();  
        }
        return $row;
    }

    public function key() {
        return $this->currentRow;
    }

    public function next() {
        //echo "next";
        $this->currentRow++;
        //$this->line = $this->readLine();
    }

    /**
     * reset all cycle settings and read first line (because next called function is current)
     * @throws \Exception
     */
    public function rewind() {
        $this->currentRow = 1;
        if ($this->options['skip_lines']) {
            $this->currentRow++;
        }
    }

    public function valid() {
        return $this->highestRow >= $this->currentRow;
    }
}

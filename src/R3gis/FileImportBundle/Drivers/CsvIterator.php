<?php

namespace R3gis\FileImportBundle\Drivers;

/**
 * iterator for csv files
 *
 * @category  Database import
 * @package   R3gis\FileImportBundle
 * @author    Daniel Degasperi <daniel.degasperi@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
class CsvIterator implements \Iterator {
    
    /**
     * filepoint of the csv file
     * @var filepointer
     */
    private $csv;
    
    /**
     * default options
     * @var array
     */
    private $options = array(
        'read_buffer' => 8192, // read buffer size
        'delimiter' => ';', // field separator char
        'enclosure' => '"', // quoted char
        'escape' => "\\",
        'trim' => false,
        'skip_lines' => 1,
        'encoding' => 'ISO-8859-15',
    );
    
    /**
     * current line
     * @var array
     */
    private $line;
    
    /**
     * current line number (excluding skipped lines)
     * @var integer
     */
    private $lineNumber = 0;
    
    /**
     * true if reached end of file
     * @var boolean
     */
    private $fileEnd;
    
    public function __construct($filename, array $options = array()) {
        $this->csv = fopen($filename, 'r');
        if (!$this->csv) {
            throw new \Exception(sprintf("Can't open file \"%s\"", $filename));
        }
        
        $this->options = array_merge($this->options, $options);
    }
    
    public function __destruct() {
        if ($this->csv) {
            fclose($this->csv);
        }
    }
    
    private function readLine() {
        // check if reached end of line
        if (feof($this->csv)) {
            $this->fileEnd = true;
            return null;
        }
        
        // read line of csv file (do not use fgetcsv, because 'locale setting is taken into account by this function')
        $lineString = fgets($this->csv, $this->options['read_buffer']);
        if ($lineString === false) {
            // there could be an error, because of a blank line at end of file
            if (feof($this->csv)) {
                $this->fileEnd = true;
                return null;
            }
            
            throw new \Exception("could not read line ".($this->lineNumber + $this->options['skip_lines']));
        }
        
        // convert linestring into utf-8 encoding
        if (strtoupper($this->options['encoding'])  !== 'UTF-8' && strtoupper($this->options['encoding'])  !== 'UTF8') {
            $lineString = iconv($this->options['encoding'], 'UTF-8', $lineString);
        }
        
        // parse csv linestring
        $line = str_getcsv($lineString, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
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
    }
    
    public function current() {
        return $this->line;
    }

    public function key() {
        return $this->lineNumber;
    }

    public function next() {
        $this->lineNumber++;
        $this->line = $this->readLine();
    }

    /**
     * reset all cycle settings and read first line (because next called function is current)
     * @throws \Exception
     */
    public function rewind() {
        $this->fileEnd = false;
        
        if (fseek($this->csv, 0) === -1) {
            throw new \Exception("could not rewind");
        }
        
        // skip lines
        for($i = 0; $i < $this->options['skip_lines']; $i++) {
            $line = fgets($this->csv);
            if ($line === false) {
                throw new \Exception("could not skip line [{$i}/{$this->options['skip_lines']}]");
            }
        }
        
        $this->lineNumber = 0;
        $this->line = $this->readLine();
    }

    public function valid() {
        $ret = true;
        if ($this->fileEnd) {
            $ret = false;
        }
        return $ret;
    }
}

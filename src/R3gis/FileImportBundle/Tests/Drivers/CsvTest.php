<?php

namespace R3gis\FileImportBundle\Tests\Drivers;

require_once __DIR__ . '/../bootstrap.php';
spl_autoload_register('R3gis_fileimportbundle_autoload');

use R3gis\FileImportBundle\Drivers\Csv;

class CsvTest extends \PHPUnit_Framework_TestCase
{
    
    private $csv;
    
    public function setUp()
    {
        $this->csv = new Csv(__DIR__ . '/Resources/with_header.csv');
        $this->csv_fixed_columns = new Csv(__DIR__ . '/Resources/fixed_columns.csv', array(
            'trim' => true,
        ));
        $this->csv_iso = new Csv(__DIR__ . '/Resources/iso885915.csv');
        $this->csv_blank_line = new Csv(__DIR__ . '/Resources/blank_line.csv');
    }


    public function testHeader()
    {
        $header = $this->csv->getHeader();
        $this->assertEquals(6, count($header));
    }
    
    public function testIterator()
    {
        $it = $this->csv->getIterator();
        
        $sumCsvDecimal = 0;
        foreach($it as $row) {
            $sumCsvDecimal += $row[5];
        }
        
        $this->assertEquals(7954, $sumCsvDecimal);
    }
    
    public function testTrim()
    {
        $it = $this->csv_fixed_columns->getIterator();
        
        foreach($it as $row) {
            $this->assertEquals('Lorem ipsum dolor sit amet', $row[0]);
        }
    }
    
    public function testIso885915()
    {
        $it = $this->csv_iso->getIterator();
        foreach($it as $row) {
            $this->assertEquals('€äüöè', $row[0]);
        }
    }
    
    
    public function testBlankLine()
    {
        $it = $this->csv_blank_line->getIterator();
        foreach($it as $row) {
            $this->assertEquals('Lorem ipsum dolor sit amet', $row[0]);
        }
    }
}

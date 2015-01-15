<?php

namespace R3gis\AppBundle\Ckan\Tests\Utility;

require_once __DIR__.'/../../../../../app/AppKernel.php';
//require_once __DIR__ . '/bootstrap.php';

use R3gis\AppBundle\Ckan\CkanUtils;

class CkanUtilsTest extends \PHPUnit_Framework_TestCase {
    
    const CKAN_BASE_URL = 'https://data.geobi.info/';
    
    public function setUp()
    {
        $this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();
 
        $this->cachePath = $this->kernel->getRootDir() . '/cache/' . $this->kernel->getEnvironment() . '/ckan/';
    }
    
    public function testImportDownload() {
        
        $ckanPackage = 'elezioni-europee-14-diversi-partiti-prova';
        $ckanId = 'a05612c3-5dc4-49ce-88c5-0a5e44f667d5';
        
        $ckan = new CkanUtils(CkanUtilsTest::CKAN_BASE_URL, $this->cachePath);
        $data = $ckan->getPackageDataFromPackageAndId($ckanPackage, $ckanId);
        $this->assertTrue(is_array($data));
        
        @mkdir("{$this->cachePath}tmp");
        $destFile = "{$this->cachePath}tmp/test.{$data['format']}";
        $ckan->downloadData($ckanPackage, $ckanId, $destFile);
        $this->assertTrue(file_exists($destFile));
    }
    
    /*public function testImportXLS() {
        
        $ckanPackage = 'elezioni-europee-14-diversi-partiti-prova';
        $ckanId = 'a05612c3-5dc4-49ce-88c5-0a5e44f667d5';
        
        $ckan = new CkanUtils(CkaneUtilsTest::CKAN_BASE_URL, $this->cachePath);
        //$data = $ckan->getPackageDataFromPackageAndId($ckanPackage, $ckanId);
        //$this->assertTrue(is_array($data));
        
        //@mkdir("{$this->cachePath}tmp");
        //$destFile = "{$this->cachePath}tmp/test.xls";
        //$ckan->importData($ckanPackage, $ckanId, $destFile);
        
        //$this->assertTrue(file_exists($destFile));
        
        
        
    }*/
    
}
<?php

namespace R3gis\AppBundle\Ckan\Tests\Utility;

require_once __DIR__.'/../../../../../app/AppKernel.php';
//require_once __DIR__ . '/bootstrap.php';

use R3gis\AppBundle\Ckan\CkanImportUtils;

class CkanImportUtilsTest extends \PHPUnit_Framework_TestCase {
    
    const CKAN_BASE_URL = 'https://data.geobi.info/';
    
    public function setUp()
    {
        $this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
 
        $this->cachePath = $this->kernel->getRootDir() . '/cache/' . $this->kernel->getEnvironment() . '/ckan/';
        
        //$this->container = $this->kernel->getContainer();
        
        //$this->logger = $this->container->get('logger'); 
    }
    
    public function cleanUp() {
        $db = $this->container->get('doctrine')->getConnection();
        $db->exec("DROP TABLE IF EXISTS impexp.import_test");
        for($i = 0; $i<20; $i++) {
            $db->exec("DROP TABLE IF EXISTS impexp.import_test_{$i}");
        }
    }
    
    public function getCkanImportUtilsOptions() {
        $opt = array(
            'database_driver'=>$this->container->getParameter('database_driver'),
            'database_host'=>$this->container->getParameter('database_host'),
            'database_port'=>$this->container->getParameter('database_port'),
            'database_name'=>$this->container->getParameter('database_name'),
            'database_user'=>$this->container->getParameter('database_user'),
            'database_password'=>$this->container->getParameter('database_password'));
        return $opt;    
        
    }
    public function testImportCsv() {
        
        // Cleanup
        $this->cleanup();
        $opt = $this->getCkanImportUtilsOptions();
        
        $ckanImport = new CkanImportUtils( $this->container->get('doctrine'), $opt );
        $data = $ckanImport->importFile(__DIR__ . '/../Resources/test.csv', 'impexp.import_test'); 
        $this->assertTrue( is_array( $data ) );
        $this->assertTrue( is_array( $data[0] ) );
        $this->assertTrue( empty( $data[0]['name'] ) );
        $this->assertTrue( !empty( $data[0]['file'] ) );
        $this->assertTrue( !empty( $data[0]['table'] ) );
    }
    
    public function testImportXls() {
        
        // Cleanup
        $this->cleanup();
        $opt = $this->getCkanImportUtilsOptions();
        
        $ckanImport = new CkanImportUtils( $this->container->get('doctrine'), $opt );
        $data = $ckanImport->importFile(__DIR__ . '/../Resources/test.xls', 'impexp.import_test'); 
        $this->assertTrue( is_array( $data ) );
        $this->assertTrue( is_array( $data[0] ) );
        $this->assertTrue( !empty( $data[0]['name'] ) );
        $this->assertTrue( !empty( $data[0]['file'] ) );
        $this->assertTrue( !empty( $data[0]['table'] ) );
    }
    
    public function testImportShp() {
        
        // Cleanup
        $this->cleanup();
        $opt = $this->getCkanImportUtilsOptions();
        
        $ckanImport = new CkanImportUtils( $this->container->get('doctrine'), $opt );
        $data = $ckanImport->importFile(__DIR__ . '/../Resources/test.shp', 'impexp.import_test'); 
        $this->assertTrue( is_array( $data ) );
        $this->assertTrue( is_array( $data[0] ) );
        $this->assertTrue( empty( $data[0]['name'] ) );
        $this->assertTrue( !empty( $data[0]['file'] ) );
        $this->assertTrue( !empty( $data[0]['table'] ) );
    }
    
    public function testImportShpZipped() {
        
        // Cleanup
        $this->cleanup();
        $opt = $this->getCkanImportUtilsOptions();
        
        $ckanImport = new CkanImportUtils( $this->container->get('doctrine'), $opt );
        $data = $ckanImport->importFile(__DIR__ . '/../Resources/idrologia.zip', 'impexp.import_test'); 
        $this->assertTrue( is_array( $data ) );
        $this->assertTrue( is_array( $data[0] ) );
        $this->assertTrue( !empty( $data[0]['name'] ) );
        $this->assertTrue( !empty( $data[0]['file'] ) );
        $this->assertTrue( !empty( $data[0]['table'] ) );
    }
    
    public function testImportZipped() {
        
        // Cleanup
        $this->cleanup();
        $opt = $this->getCkanImportUtilsOptions();
       
        $ckanImport = new CkanImportUtils( $this->container->get('doctrine'), $opt );
        $data = $ckanImport->importFile(__DIR__ . '/../Resources/distrettiagrariemasi.zip', 'impexp.import_test'); 
        // print_r($data); die();
        $this->assertTrue( is_array( $data ) );
        $this->assertTrue( is_array( $data[0] ) );
        $this->assertTrue( !empty( $data[0]['name'] ) );
        $this->assertTrue( !empty( $data[0]['file'] ) );
        $this->assertTrue( !empty( $data[0]['table'] ) );
    }
    
    
}
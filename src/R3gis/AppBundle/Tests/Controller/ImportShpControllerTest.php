<?php

namespace R3gis\AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use R3gis\AppBundle\Ckan\CkanImportUtils;

class ImportControllerTest extends WebTestCase
{

    public function setUp() {
        /*$this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();

        $this->container = $this->kernel->getContainer();

        $this->logger = $this->container->get('logger');*/
        
        //self::bootKernel();
        /*$this->em = static::$kernel->getContainer()
            ->get('doctrine')
            ->getManager()
        ;*/
        
        static::$kernel = static::createKernel();
        static::$kernel->boot();
        $this->container = static::$kernel->getContainer();
        $this->doctrine = static::$kernel->getContainer()->get('doctrine');
        $this->db = $this->doctrine->getConnection();
        //$this->em = static::$kernel->getContainer()
        //    ->get('doctrine')
        //    ->getManager();
        
        
        
        
    }
    
    public function testCkanTables() {
        /*$client = static::createClient();

        $client->request('GET', 'http://geobi.r3-gis/api/1/ckan/idrologia/8910f0b0-25f7-4d3c-92fa-2cf779598057/tables.json');
        //print_r( $client->getResponse()->getContent() );
        $this->assertEquals($client->getResponse()->getStatusCode(), 200);
        $jsonText = $client->getResponse()->getContent();
        $json = json_decode($jsonText, true);
        $this->assertTrue(is_array($json));*/
        
        $db = $this->db;
         
        $db->beginTransaction();

        $opt = array(
            'database_driver' => $this->container->getParameter('database_driver'),
            'database_host' => $this->container->getParameter('database_host'),
            'database_port' => $this->container->getParameter('database_port'),
            'database_name' => $this->container->getParameter('database_name'),
            'database_user' => $this->container->getParameter('database_user'),
            'database_password' => $this->container->getParameter('database_password'));

        $srcFile = '/data/sites/geobi/geobi/src/R3gis/AppBundle/Tests/Resources/accent.zip';
        $fqDestTable = 'impexp.test';
        
        $ckanImport = new CkanImportUtils($this->doctrine, $opt);
        $data = $ckanImport->importFile($srcFile, $fqDestTable);
        
        // Check UTF-8
        $this->assertEquals( $data[0]['name'], "Qualità biologica delle acque IBE" );
        
        //print_r($data);

        //die('fine');
    }

    
    
    
}

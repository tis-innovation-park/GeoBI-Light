<?php

namespace R3gis\AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportControllerTest extends WebTestCase
{
    public function testImport()
    {
        $client = static::createClient();

        $client->request('GET', '/api/1/import/import.json');
        //print_r( $client->getResponse()->getContent() );
        $this->assertEquals($client->getResponse()->getStatusCode(), 200);
        $jsonText = $client->getResponse()->getContent();
        $json = json_decode($jsonText, true);
        $this->assertTrue(is_array($json));
    }
    
    public function testImportShp()
    {
        $client = static::createClient();

        $client->request('GET', '/api/1/import/import_2.json', array('package'=>'distretti-agrari-e-masi-alto-adige',
                                                               'id'=>'910a42d5-b7b1-4d80-a2c6-cd07990cca9a'));
        $this->assertEquals($client->getResponse()->getStatusCode(), 200);
        $jsonText = $client->getResponse()->getContent();
        $json = json_decode($jsonText, true);
        $this->assertTrue(is_array($json));
    }
        
    public function testImportCsv()
    {
        $client = static::createClient();

        $client->request('GET', '/api/1/import/import_2.json', array('package'=>'eleizoni-europee-2014',
                                                               'id'=>'b3f1dbaa-499f-4031-9e0a-68c46b1f35b3'));
        $this->assertEquals($client->getResponse()->getStatusCode(), 200);
        $jsonText = $client->getResponse()->getContent();
        $json = json_decode($jsonText, true);
        $this->assertTrue(is_array($json));
    }
    
    
    
    
}

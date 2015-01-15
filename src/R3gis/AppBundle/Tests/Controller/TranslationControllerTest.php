<?php

namespace R3gis\AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TranslationControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();
        
        foreach( array('it', 'de', 'en') as $lang) {
            foreach( array('app', 'map') as $name) {
                $route = "/api/1/translations/{$lang}/{$name}.json";
                $client->request( "GET", $route );
                $this->assertEquals( $client->getResponse()->getStatusCode(), 200 );
                
                $jsonText = $client->getResponse()->getContent();
                $json = json_decode($jsonText, true);
                $this->assertTrue( $json['success']);
            }
        }
    }
    
    public function testInvalidRoute()
    {
        $client = static::createClient();
        
        foreach( array('es', 'fr', 'kr') as $lang) {
            foreach( array('app', 'map') as $name) {
                $route = "/api/1/translations/{$lang}/{$name}.json";
                $client->request( "GET", $route );
                
                //print_r( $client->getResponse()->getContent() );
                //die();
                
                $this->assertEquals( $client->getResponse()->getStatusCode(), 404 );
                
                //$jsonText = $client->getResponse()->getContent();
                //$json = json_decode($jsonText, true);
                //$this->assertTrue( $json['success']);
            }
        }
    }
}

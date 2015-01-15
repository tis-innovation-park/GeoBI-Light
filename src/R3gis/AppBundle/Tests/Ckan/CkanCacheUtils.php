<?php

namespace R3gis\AppBundle\Ckan\Tests\Utility;

require_once __DIR__.'/../../../../../app/AppKernel.php';
//require_once __DIR__ . '/bootstrap.php';

use R3gis\AppBundle\Ckan\CkanCacheUtils;

class CkanCacheUtilsTest extends \PHPUnit_Framework_TestCase {
    
    const CKAN_BASE_URL = 'https://data.geobi.info/';
    
    public function setUp()
    {
        $this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
        
        $this->cachePath = $this->kernel->getRootDir() . '/cache/' . $this->kernel->getEnvironment() . '/ckan/';
    }
    
    public function testPurge() {
        $ckanCache = new CkanCacheUtils($this->container->get('doctrine'));
        $ckanCache->purge();
    }
    
    
}
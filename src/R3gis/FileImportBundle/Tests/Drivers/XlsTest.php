<?php

namespace R3gis\FileImportBundle\Tests\Drivers;

require_once __DIR__.'/../../../../../../app/AppKernel.php';
require_once __DIR__ . '/../bootstrap.php';
spl_autoload_register('R3gis_fileimportbundle_autoload');

use R3gis\FileImportBundle\Drivers\Xls;

class XlsTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();
 
        $this->container = $this->kernel->getContainer();
        
        $this->logger = $this->container->get('logger'); 
    }

    public function testTest()
    {
        $xls = new Xls(__DIR__ . '/Resources/test01.xls');
        $xls->load();
        
        $this->assertEquals($xls->getSheetCount(), 3); 
        $this->assertEquals(count($xls->getHeader()), 5); 
        $this->assertEquals(count($xls->getNormalizedHeader()), 4); 
                
        $header = $xls->getNormalizedHeader();

        $count = 0;
        foreach($xls->getIterator() as $key=>$row) {
            $count++;
        }
        $this->assertEquals($count, 7); 
    }
    
}

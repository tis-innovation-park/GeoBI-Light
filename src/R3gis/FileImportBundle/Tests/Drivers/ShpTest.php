<?php

namespace R3gis\FileImportBundle\Tests\Drivers;

require_once __DIR__ . '/../../../../../../app/AppKernel.php';
require_once __DIR__ . '/../bootstrap.php';
spl_autoload_register('R3gis_fileimportbundle_autoload');

use R3gis\FileImportBundle\Drivers\Shp;

class ShpTest extends \PHPUnit_Framework_TestCase {

    // private $shp;
    private $kernel;
    private $container;
    private $logger;

    public function setUp() {
        $this->kernel = new \AppKernel('test', true);
        $this->kernel->boot();

        $this->container = $this->kernel->getContainer();

        $this->logger = $this->container->get('logger');
    }

    public function testTest() {
        $shp = new Shp(__DIR__ . '/Resources/ortofoto.shp');
        $this->initDriver($shp);

        $this->removeTestTables('public.import01_test');

        $shp->setSrid(4326);
        $dmpFile = $shp->shp2sql('public.import01_test');
        $shp->sql2pgsql($dmpFile);

        unlink($dmpFile);
        $this->removeTestTables('public.import01_test');
    }

    public function initDriver(Shp $shp) {

        $shp->setLogger($this->logger)
                ->setTempDir(__DIR__ . '/temp/')
                ->setDatabaseDriver($this->container->getParameter('database_driver'))
                ->setDatabaseHost($this->container->getParameter('database_host'))
                ->setDatabasePort($this->container->getParameter('database_port'))
                ->setDatabaseName($this->container->getParameter('database_name'))
                ->setDatabaseUser($this->container->getParameter('database_user'))
                ->setDatabasePassword($this->container->getParameter('database_password'));
    }

    public function removeTestTables($table) {
        // TODO: use doctrine
        $db = new \PDO("pgsql:dbname=" . $this->container->getParameter('database_name') .
                ";host=" . $this->container->getParameter('database_host') .
                ";port=" . $this->container->getParameter('database_port'), $this->container->getParameter('database_user'), $this->container->getParameter('database_password'));
        $sql = "DROP TABLE IF EXISTS {$table}";
        $db->exec($sql);
    }

}

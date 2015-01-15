<?php

namespace R3gis\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use R3gis\AppBundle\Ckan\CkanUtils;
use R3Gis\Common\FileImportBundle\Drivers\Csv;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="r3gis.app.default.index")
     */
    public function indexAction()
    {
        
        $params = array(
            'baseUrl'=>$this->container->getParameter('base_url'),
            'authorUrl'=>$this->container->getParameter('author_url'),
            'srid'=>$this->container->getParameter('srid'));
        return $this->render('R3gisAppBundle:Default:index.html.twig', $params);
    }
    
}

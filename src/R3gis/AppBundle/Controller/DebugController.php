<?php

namespace R3gis\AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use R3gis\AppBundle\Ckan\CkanUtils;
use R3Gis\Common\FileImportBundle\Drivers\Csv;

/**
 * @Route("/debug")
 */

class DebugController extends Controller
{
    /**
     * @Route("/", name="r3gis.app.debug.index")
     */
    public function indexAction()
    {
        $data = array('url_prefix' => '../api/1/');
        return $this->render('R3gisAppBundle:Default:debug.html.twig', $data);
    }
    
    /**
     * @Route("/map/{hash}", name="r3gis.app.debug.maps")
     */
    public function mapAction(Request $request, $hash)
    {
        $data = array('hash' => $hash);
        return $this->render('R3gisAppBundle:Default:debug_map.html.twig', $data);
    }
    
}

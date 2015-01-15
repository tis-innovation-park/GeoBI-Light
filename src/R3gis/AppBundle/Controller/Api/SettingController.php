<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

/**
 * @Route("/setting")
 */
class SettingController extends Controller {

    /**
     * @Route("/{lang}/{name}.json", requirements={"lang" = "en|it|de"}, methods = {"GET"}, name="r3gis.app.seting.list")
     * @Cache(expires="+600 seconds", maxage="600", smaxage="600")
     */
    public function listAction(Request $request, $name, $lang) {
        $response = new JsonResponse();

        // SS: Verificare percorso traduzioni e sistema traduzioni
        $fileName = basename($name);
        $settingFileName = $this->get('kernel')->locateResource("@R3gisAppBundle/Resources/setting/{$fileName}_{$lang}.json");
        if (!file_exists($settingFileName)) {
            // @SS: Change exception
            throw new \Exception("Resource not found");
        }

        $content = file_get_contents($settingFileName);

        // Set the last modified date
        $date = new \DateTime();
        $date->setTimestamp(filemtime($settingFileName));
        $response->setLastModified($date);

        // Set e-tag
        $response->setETag(md5($content));
        $response->setPublic(); // assicurarsi che la risposta sia pubblica
        $response->isNotModified($request);

        // SS: Solo se hash diverso
        $jsonData = json_decode($content);
        $response->setData(array(
            'success' => true,
            'result' => array('data' => $jsonData)
        ));

        return $response;
    }
    
    /**
     * @Route("/global.json", methods = {"GET"}, name="r3gis.app.seting.global")
     * @Cache(expires="+600 seconds", maxage="600", smaxage="600")
     */
    public function globalAction(Request $request) {
        
        $params = array(
            'baseUrl'=>$this->container->getParameter('base_url'),
            'authorUrl'=>$this->container->getParameter('author_url'),
            'srid'=>$this->container->getParameter('srid'));
        
        $response = new JsonResponse();
        $response->setData(array(
            'success' => true,
            'result' => array('data' => $params)
        ));
        
        return $response;
    }

}

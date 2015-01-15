<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

/**
 * @Route("/translation")
 */
class TranslationController extends Controller {

    /**
     * @Route("/{lang}/{name}.json", requirements={"lang" = "en|it|de", "name" = "(homepage|app|user|map)[\-\w]*"}, methods = {"GET"}, name="r3gis.app.translation.list")
     * @Cache(expires="+600 seconds", maxage="600", smaxage="600")
     */
    public function listAction(Request $request, $name, $lang) {
        $response = new JsonResponse();

        // SS: Verificare percorso traduzioni e sistema traduzioni
        list($fileName) = explode('-', basename($name));
        $translationFileName = $this->get('kernel')->locateResource("@R3gisAppBundle/Resources/translation/{$fileName}_{$lang}.json");

        $content = file_get_contents($translationFileName);

        // Set the last modified date
        $date = new \DateTime();
        $date->setTimestamp(filemtime($translationFileName));
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

}

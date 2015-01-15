<?php

namespace R3gis\AppBundle\Controller\Api;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/error")
 */
class ErrorController extends Controller{
    
    /**
     * 
     * @Route("/{lang}/{code}")
     */
    public function errorAction(Request $request, $lang="", $code="") {
        $response = new JsonResponse();
        
        $params = $request->query->all();
        ksort($params);
        
        $translationFileName = $this->get('kernel')->locateResource("@R3gisAppBundle/Resources/error/error_{$lang}.json");
        
        $content = file_get_contents($translationFileName);
        $errors = json_decode($content, true);
        $errorText = vsprintf($errors[$code], $params);
        
        /* if we use etag, we should consider parameters as well.
        // Set the last modified date
        $date = new \DateTime();
        $date->setTimestamp(filemtime($translationFileName));
        $response->setLastModified($date);

        // Set e-tag
        $response->setETag(md5($errorText));
        $response->setPublic(); 
        $response->isNotModified($request);*/

        $response->setData(
            array(
                'success' => true,
                'result' => $errorText
            )
        );

        return $response;
    }    
}

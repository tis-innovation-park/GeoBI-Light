<?php

namespace R3gis\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * @Route("/social")
 */
class SocialController extends Controller {
    
    /**
     * @Route("/map/{hash}", methods = {"GET"})
     */
    public function mapAction(Request $request, $hash='') {
        
        $useragent = $request->headers->get('User-Agent');
        $found = preg_match('/(facebookexternalhit\/[0-9])|(Facebot)|(Twitterbot)|(LinkedInBot)|(Pinterest)|(Google.*snippet)/', $useragent);
        if(0 === $found) {
            return $this->redirect("/#/map/".$hash);
        }
        
        $mapinfo = $this->getMapInfo($hash)['map'];

        $params = array(
            'maphash'=>$hash,
            'mapinfo'=>$mapinfo,
        );
        return $this->render('R3gisAppBundle:Default:social.map.html.twig', $params);
    }
    
    //@TODO avoid redundancy, reuse code from other controller/export to service or something.
    private function getMapInfo($hash) {

        $map = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:Map')
                ->findOneByHash($hash);
        if (empty($map)) {
            throw new ApiException(ApiException::NOT_FOUND, 'Map not found');
        }

        //$extent = $this->getMapExtent($map);
        //if (empty($extent)) {
          //  $extent = explode(' ', MapController::DEFAULT_EXTENT);
        //}

        // Custom normalization of datetime
        $normalizer = new GetSetMethodNormalizer();
        $callback = function ($dateTime) {
            return $dateTime instanceof \DateTime ? $dateTime->format(\DateTime::ISO8601) : '';
        };
        $normalizer->setCallbacks(array('insDate' => $callback, 'modDate' => $callback));

        $serializer = new Serializer(array($normalizer), array(new JsonEncoder()));
        $mapData = $serializer->normalize($map);

        $backgroundLayer = array(
            'name' => null,
            'order' => 0,
            'type' => 'background',
            'active' => $mapData['backgroundType'] != 'none',
            'options' => array('source' => $mapData['backgroundType']));

        $mapInfo = array();
        $mapInfo['name'] = $mapData['name'];
        $mapInfo['description'] = $mapData['description'];
        $mapInfo['private'] = $mapData['private'];
        $mapInfo['temporary'] = $mapData['temporary'];
        $mapInfo['displayProjection'] = 'EPSG:3857';
        //$mapInfo['extent'] = $extent;
        $mapInfo['userExtent'] = $mapData['userExtent'];
        //$mapInfo['layers'] = array_merge(array($backgroundLayer), $this->getMapLayers($map));
        $mapInfo['clickCount'] = $mapData['clickCount'];
        $mapInfo['language'] = $mapData['language']['id'];
        $mapInfo['user'] = array('name' => $mapData['user']['name']);
        $mapData['map'] = $mapInfo;

        // unset some info
        foreach (array('language', 'user', 'idParent', 'clickCount', 'userExtent', 'backgroundType', 'description', 'name', 'id', 'insDate', 'modDate', 'private', 'temporary') as $delKey) {
            unset($mapData[$delKey]);
        }
//print_r($mapData);
        return $mapData;
    }
}

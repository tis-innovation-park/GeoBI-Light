<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use R3gis\AppBundle\Utils\MapCreatorUtils;
use R3gis\AppBundle\Controller\Api\MapController;
use R3gis\AppBundle\Entity\Map;
use R3gis\AppBundle\Exception\ApiException;

/**
 * @Route("/import")
 */
class ImportController extends Controller {

    // SS: Utility class or extend controller
    // If the request is application/json replace parameters
    protected function purgeRequest(Request $request) {

        // @see http://silex.sensiolabs.org/doc/cookbook/json_request_body.html for post request in application
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }
        return $request;
    }

    /**
     * @Route("/ckan/{package}/{id}/{table}", methods = {"POST"}, name="r3gis.api.import.from_ckan"))
     */
    public function fromCkanAction(Request $request, $package, $id, $table) {

        $request = $this->purgeRequest($request);

        $response = new JsonResponse();

        $spatialColumn = $request->request->get('spatial_column');
        $dataColumn = $request->request->get('data_column');
        $lang = $request->request->get('lang', 'en');
        $order = $request->request->get('order', null);
        $duplicate = true; // $request->request->get('duplicate') === 'true';
        $hash = $request->request->get('hash');

        if ($duplicate && empty($hash)) {
            throw new \Exception("Missing hash");
        }
        if ($dataColumn == '') {
            $dataColumn = null;
        }

        $db = $this->getDoctrine()->getConnection();

        $db->beginTransaction();
        $mapUtils = new MapCreatorUtils($this->getDoctrine());



        if (!empty($hash)) {
            // Hash present: 

            $map = $this->getDoctrine()->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
            if (empty($map)) {
                throw new \Exception("Map not found");
            }

            if ($duplicate) {
                // Duplicate the current map and return the new hash. Set the temporary flag
                $mapUtils = new MapCreatorUtils($this->getDoctrine());
                $user = $this->getUser();
                $hash = $mapUtils->duplicateMap($user, $hash, true);
                $map = $this->getDoctrine()->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
                $mapUtils->setMap($map);
                $mapUtils->setLang($map->getLanguage()->getId());
            }
        } else {
            // Create a new map

            $hash = $mapUtils->createEmptyMap(1, $lang); // 1, $ckanPackage, $ckanId, $sheet, $spatialColumn, $dataColumn, $lang);
        }

        $layer = $mapUtils->createLayerDataFromCache($package, $id, $table, $spatialColumn, $dataColumn);

        $mapUtils->setDefaults($layer);

        if ($order >= 1) {  // if order
            $em = $this->getDoctrine()->getManager();
            
            $oldLayer = $this->getDoctrine()->getRepository('R3gisAppBundle:MapLayer')->findOneBy(array('map' => $map, 'order'=>$order));
            if (empty($oldLayer)) {
                throw new ApiException(ApiException::NOT_FOUND, 'Later 1 not found');
            }
            $oldLayer->setModDate($layer->getModDate());
            $oldLayer->setTableSchema($layer->getTableSchema());
            $oldLayer->setTableName($layer->getTableName());
            $oldLayer->setCkanPackage($layer->getCkanPackage());
            $oldLayer->setCkanId($layer->getCkanId());
            $oldLayer->setCkanSheet($layer->getCkanSheet());
            $oldLayer->setIsShape($layer->getIsShape());
            $oldLayer->setDataColumn($layer->getDataColumn());
            $oldLayer->setSpatialColumn($layer->getSpatialColumn());
            $oldLayer->setSpatialColumnHeader($layer->getSpatialColumnHeader());
            $oldLayer->setDataColumnHeader($layer->getDataColumnHeader());
            
            $em->persist($oldLayer);
            $em->flush();
            //$em->detach($oldLayer);
            
            $em->remove($layer);
            //$em->flush();
            //$em->detach($layer);
            
            //$em->persist($map);
            $em->refresh($map);
            
        }
        $db->commit();
        $jsonResponse = $this->forward('R3gisAppBundle:Api\Map:info', array(
            'hash' => $hash
        ));

        $jsonData = json_decode($jsonResponse->getContent(), true);
        if (empty($jsonData['success'])) {
            throw new \Exception("Invalid response from Api\Map controller");
        }
        $result = array(
            'hash' => $hash,
            'map' => $jsonData['result']['map']);
        $response->setData(array(
            'success' => true,
            'result' => $result
        ));

        return $response;
    }

}

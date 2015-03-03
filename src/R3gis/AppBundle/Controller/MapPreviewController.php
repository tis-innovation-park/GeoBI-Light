<?php

namespace R3gis\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Filesystem\Filesystem;
use R3gis\AppBundle\Utils\MapCreatorUtils;
use R3gis\AppBundle\Entity\Map;
use R3gis\AppBundle\Entity\MapLayer;
use R3gis\AppBundle\Entity\MapClass;
use R3gis\AppBundle\Utils\Division\DivisionFactory;
use R3gis\AppBundle\Controller\Api\MapController;
use GuzzleHttp\Client;

/**
 * @Route("/map/stat")
 */
class MapPreviewController extends Controller {

    const LIMIT = 50;  // Max 50 rows
    const DEFAULT_EXTENT = '737779 4231225 2061823 5957355';

    /**
     * @Route("/{hash}/{width}/{height}/preview.png", requirements={"width" = "\d+", "height" = "\d+"}, methods = {"GET"}, name="r3gis.map.preview")
     * @Cache(expires="+600 seconds", maxage="600", smaxage="600")
     */
    public function mapPreviewAction(Request $request, $hash, $width, $height) {

        $kernel = $this->get('kernel');
        $logger = $this->get('logger');
        $cachePath = $kernel->getRootDir() . '/cache/' . $kernel->getEnvironment() . '/preview/';
        $extentRequest = $request->query->get('extent');
        $isDownload = $request->query->get('download') !== null;
        $logger->error("MAP-PREVIEW START [NO ERROR]");
        
        $fs = new Filesystem();
        if (!$fs->exists($cachePath)) {
            $logger->info("Creating cache path {$cachePath}");
            $fs->mkdir($cachePath);
        }
        
        $cacheFile = "{$cachePath}preview_{$width}x{$height}_{$hash}";
        $image = null;
        try {
            $map = $this->getDoctrine()
                    ->getRepository('R3gisAppBundle:Map')
                    ->findOneByHash($hash);
            if (empty($map)) {
                throw new \Exception('Map not found');
            }

            $fileDate = null;
            if ($fs->exists($cacheFile)) {
                $d = new \DateTime();
                $fileDate = $d->setTimestamp ( filemtime ( $cacheFile ));
            }
            $fileDate = null; 
            if (empty($fileDate) || $fileDate < $map->getModDate() || !empty($extentRequest)) {
                // Empty or old cache
                $logger->info("Generating preview map");
                
                //take extent from request if avalaible, else multiple fallbacks.
                if(!empty($extentRequest)) {
                    $extent = explode(',', $extentRequest);
                }
                if(empty($extent)|| count($extent)!=4) {
                    $extent = $map->getUserExtent();
                }
                if(empty($extent)) {
                    $extent = $this->getMapExtent($map);
                }
                if (empty($extent)) {
                    $extent = explode(' ', MapController::DEFAULT_EXTENT);
                }
                $layers = $this->getMapLayers($map);

                $httpClient = new Client();
                $url = $this->container->getParameter('author_url');
                if (substr($url, -1) != '/') {
                    $url .= '/';
                }
                $url .= 'services/download.php';
                
                $logger->info("Getting preview from {$url}");
                
                $params = array();

                $httpRequest = $httpClient->createRequest('POST', $url);
                $postBody = $httpRequest->getBody();

                $postBody->setField('viewport_size[0]', $width);
                //$logger->debug("viewport_size[0]={$width}");
                
                $postBody->setField('viewport_size[1]', $height);
                //$logger->debug("viewport_size[1]={$height}");
                
                $postBody->setField('format', 'png');
                //$logger->debug("format=png");
                
                $postBody->setField('extent', "{$extent[0]},{$extent[1]},{$extent[2]},{$extent[3]}");
                //$logger->debug("extent[1]={$extent[0]},{$extent[1]},{$extent[2]},{$extent[3]}");
                
                $postBody->setField('dpi', '96');
                //$logger->debug("dpi=96");
                
                $postBody->setField('srid', 'EPSG:3857');
                //$logger->debug("srid=EPSG:3857");
                
                $postBody->setField("scalebar", '');  // Prevent scale bar to generate 
                //$logger->debug("scalebar=");                

                $layerNo = 0;
                $layers = $this->getMapLayers($map);
                //print_r($layers);
                //rsort($layers);
                //print_r($layers); die();
                foreach ($layers as $layer) {
                    if ($layer['options']['active']) {
                        $wmsUrl = $this->container->getParameter('base_url');
                        if (substr($wmsUrl, -1) != '/') {
                            $wmsUrl .= '/';
                        }
                        $wmsUrl .= "map/stat/{$hash}/stat/{$layer['order']}";

                        $logger->debug("wms layer: {$wmsUrl}");
                    
                        $postBody->setField("tiles[{$layerNo}][url]", $wmsUrl);
                        $postBody->setField("tiles[{$layerNo}][parameters][SERVICE]", 'WMS');
                        $postBody->setField("tiles[{$layerNo}][parameters][VERSION]", '1.1.1');
                        $postBody->setField("tiles[{$layerNo}][parameters][REQUEST]", 'GetMap');
                        $postBody->setField("tiles[{$layerNo}][parameters][SRS]", 'EPSG:3857');
                        $postBody->setField("tiles[{$layerNo}][parameters][LAYERS]", 'stat');
                        $postBody->setField("tiles[{$layerNo}][parameters][FORMAT]", 'image/png; mode=8bit');
                        $postBody->setField("tiles[{$layerNo}][parameters][TRANSPARENT]", 'true');
                        $postBody->setField("tiles[{$layerNo}][parameters][GISCLIENT_MAP]", '1');
                        $postBody->setField("tiles[{$layerNo}][opacity]", $layer['options']['opacity']);
                    
                        $layerNo++;
                    }
                }

                $httpResponse = $httpClient->send($httpRequest);

                $code = $httpResponse->getStatusCode();
                $reason = $httpResponse->getReasonPhrase();

                $httpResponse->getBody();
                
                //echo "Response: {$code} {$reason}\n<br>\n";
                $logger->debug("Response: {$code} {$reason}");
                
                $responseBody = $httpResponse->getBody();

                if ($code == 200) {
                    $responseJson = json_decode($responseBody, true);
                    // Check
                    //print_r($responseJson);
                    if ($responseJson['result'] == 'ok') {
                        $httpGetImageClient = new Client();
                        $imageResponse = $httpGetImageClient->get($responseJson['file']);
                        
                        $image = $imageResponse->getBody();
                        
                        //dont save to cache when extent was specified in request.
                        if(empty($extentRequest)){
                            $logger->info("Cache image to {$cacheFile}");
                            file_put_contents($cacheFile, $image);
                        }
                    } else {
                        $logger->info("Server response: " . print_r($responseJson, true));
                    }
                } else {
                    $logger->info("Invalid response [{$code}]: No image generated");
                }
            } else {
                // Cache present and update
                $logger->info("Returning image from cache ({$cacheFile})");
                $image = file_get_contents($cacheFile);
            }
        } catch (\Exception $e) {
            $logger->error( $e->getMessage() );
            die();
        }
        if (empty($image)) {
            $defaultPreview = $kernel->locateResource('@R3gisAppBundle/Resources/images/default_preview.png');
            $logger->info("No image found. Return default ({$cacheFile})");
            $image = file_get_contents($defaultPreview);
        }
        // echo $image; die();
        $logger->error("MAP-PREVIEW DONE [NO ERROR]");
        if ($isDownload) {
            $mapName = $map->getName();
            $mapName = mb_convert_encoding( $mapName, 'ISO-8859-1', 'UTF-8');
            $mapName = str_replace(array('"', "'", '?', '*'), '_', $mapName);
            return new Response($image, 200, array('Content-Type' => 'image/png', 'Content-Disposition' => sprintf('attachment; filename="%s"', "{$mapName}.png")));
        } else {
            return new Response($image, 200, array('Content-Type' => 'image/png'));
        }    
    }

    // SS: Move to utility class (Used by MapController)
    private function getMapExtent(Map $map) {

        $db = $this->getDoctrine()->getManager()->getConnection();

        $result = null;

        $mapLayers = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:MapLayer')
                ->findBy(array('map' => $map));

        $sqlPart = array();
        foreach ($mapLayers as $mapLayer) {
            $fqTable = $mapLayer->getTableSchema() . '.' . $mapLayer->getTableName();
            if ($mapLayer->getIsShape()) {
                $sqlPart[] = "SELECT the_geom FROM {$fqTable}";
            } else {
                $sqlPart[] = "SELECT the_geom FROM {$fqTable} INNER JOIN data.area using(ar_id)";
            }
        }
        if (count($sqlPart) > 0) {
            $sql = "SELECT st_extent(the_geom) FROM (" . implode(" UNION ", $sqlPart) . ") AS foo";
            $box = $db->query($sql)->fetchColumn();
            if (!empty($box)) {
                foreach (explode(',', str_replace(array('BOX(', ')', ' '), array('', '', ','), $box)) as $val) {
                    $result[] = round($val);
                }
            }
        }
        return $result;
    }

    private function getMapLayers(Map $map) {
        $result = array();
        $mapLayers = $this->getDoctrine()
                ->getRepository('R3gisAppBundle:MapLayer')
                ->findBy(array('map' => $map), array('order' => 'ASC'));
        foreach ($mapLayers as $mapLayer) {
            $layerInfo = MapController::getMapLayer($this->getDoctrine(), $mapLayer);
            $result[] = array(
                'name' => $layerInfo['name'],
                'order' => $layerInfo['order'],
                'type' => 'statistic',
                'active' => true,
                'options' =>
                array_diff_key($layerInfo, array_fill_keys(array(
                    'name',
                    'order'), null
            )));
        }
        return $result;
    }

}

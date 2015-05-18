<?php

namespace R3gis\AppBundle\Ckan;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use R3gis\AppBundle\Exception\ApiException;

final class CkanUtils {

    const CACHE_TTL = 864000;  // 1 day

    /**
     *
     * @var string
     */

    private $baseUrl;

    /**
     *
     * @var Client
     */
    private $httpClient;

    /**
     *
     * @var array
     */
    private $allowedFormats;

    /**
     *
     * @var type  @type LoggerInterface
     */
    private $logger;

    /**
     * 
     * @param string $baseUrl    The base url
     */
    public function __construct($baseUrl, $baseCachePath) {
        if ($baseUrl == '') {
            throw new \Exception("CkanUtils error: Empty base url");
        }
        if ($baseCachePath == '') {
            throw new \Exception("CkanUtils error: Empty base cache path");
        }

        if ($baseUrl[strlen($baseUrl) - 1] != '/') {
            $baseUrl .= '/';
        }
        if ($baseCachePath[strlen($baseCachePath) - 1] != '/') {
            $baseCachePath .= '/';
        }

        $this->baseUrl = $baseUrl;

        $this->allowedFormats = array('csv', 'xls', 'shp'); // SS: passare...
        // SS: See cache system
        $urlPart = parse_url($this->baseUrl);
        $this->cachePath = $baseCachePath . "http/ckan/{$urlPart['host']}/";

        $fs = new Filesystem();
        if (!$fs->exists($this->cachePath)) {
            $fs->mkdir($this->cachePath);
        }

        $this->httpClient = new Client();
    }

    private function log($level, $message, array $context = array()) {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * set the logger object
     * @param \Psr\Log\LoggerInterface $logger
     * @return \R3Gis\Common\FileImportBundle\Drivers\Csv
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
        return $this;
    }

    // Return data from cache or http request. Cache refreshed
    private function getJson($url, $ttl = 0) {
        $hash = md5($url);

        $fs = new Filesystem();
        $fileName = "{$this->cachePath}{$hash}.json";
        if ($ttl <= 0 || !$fs->exists($fileName) || filemtime($fileName) + $ttl < time()) {

            $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Get {$url}, ttl={$ttl}");
            $response = $this->httpClient->get($url);
            // SS: Verificare http error ...
            $json = $response->json();

            if (empty($json['success']) || $json['success'] !== true) {
                $this->log(\Psr\Log\LogLevel::ERROR, __METHOD__ . ": getJson error: Result is not a json");
                throw new \Exception("getJson error: Result is not a json");
            }
            $tmpFileName = $fileName . '.' . uniqid();
            file_put_contents($tmpFileName, json_encode($json));
            $fs->rename($tmpFileName, $fileName, true);
        } else {

            $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Get [from cache] {$url}");

            $jsonText = file_get_contents("{$this->cachePath}{$hash}.json");
            $json = json_decode($jsonText, true);
        }
        return $json;
    }

    // SS: get from cache. Maybe a "force" parameter
    /**
     * 
     * @return type
     * @throws \ExceptionReturn the package list
     */
    public function getPackageList() {
        $url = "{$this->baseUrl}api/3/action/package_list";

        $json = $this->getJson($url);

        return $json['result'];
    }

    // SS: Elimina a caso alcuni package per il reload
    public function purgePackageList($max) {
        $fs = new Filesystem();

        $files = array();
        $seq = 0;
        foreach (glob("{$this->cachePath}*.json") as $fileName) {
            $seq++;
            $files[filemtime($fileName) . sprintf('%05d', $seq)] = $fileName;
        }
        ksort($files);
        $fs = new Filesystem();
        foreach ($files as $file) {
            if ($max <= 0) {
                break;
            }
            $max--;
            $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Purge {$file}");
            $fs->remove($file);
        }
    }

    private function extractFormat($resourceName, $format) {
        if (!empty($format)) {
            $format = strtolower($format);
        } else {
            // Extracting format from extension
            $ext = strtolower(strrchr($resourceName, '.'));
            $format = ($ext === false) ? '' : substr($ext, 1);
        }
        // SS: Convert format here
        if (in_array($format, array('shapefile'))) {
            $format = 'shp';
        }
        return $format;
    }

    private function customSort($a, $b) {
        if ($a['name'] == $b['name']) {
            return 0;
        }
        return (strtoupper($a['name']) > strtoupper($b['name'])) ? 1 : -1;
    }

    /**
     * Return the list of packages with recognized data
     * @param array $packages  list of packages
     */
    // SS: get from cache. Maybe a "force" parameter
    // SS: Restituisce un array ordinato per titolo
    public function sanitarizePackageList(array $packages, $force = false) {

        $ttl = CkanUtils::CACHE_TTL;

        //if ($force) {
            $ttl = 0;
        //}
        $result = array();
        foreach ($packages as $packageCode) {
            if (empty($packageCode)) {
                continue;
            }
            $url = "{$this->baseUrl}api/3/action/package_show?id={$packageCode}";
            //echo "[$url]\n";
            $json = $this->getJson($url, $ttl);
            if ($json['result']['state'] == 'active' && $json['result']['type'] == 'dataset') {
                $res = array(
                    'id' => $packageCode, //$json['result']['id'],
                    'name' => $json['result']['title'],
                    'description' => $json['result']['notes'],
                    'valid' => null,
                    'resources' => array());
                foreach ($json['result']['resources'] as $resource) {
                    if ($resource['state'] == 'active') {
                        $resourceName = basename($resource['url']);
                        $format = $this->extractFormat($resource['url'], $resource['format']);
                        if (in_array($format, $this->allowedFormats)) {
                            // Calculate the last modified date (SS: See docs)
                            $defs = array_merge(array('created' => null,
                                'last_modified' => null,
                                'revision_timestamp' => null,
                                'cache_last_updated' => null,
                                'webstore_last_updated' => null), $resource);
                            $lastModifiedDate = max($defs['created'], $defs['last_modified'], $defs['revision_timestamp'], $defs['cache_last_updated'], $defs['webstore_last_updated']);

                            $res['resources'][] = array('id' => $resource['id'],
                                'name' => $resource['name'],
                                'format' => $format,
                                'last_modified' => substr($lastModifiedDate, 0, 19),
                                'description' => $resource['description'],
                                'url' => $resource['url'],);
                        }
                    }
                }
                if (count($res['resources']) > 0) {
                    // Sort resources node
                    usort($res['resources'], array("R3gis\AppBundle\Ckan\CkanUtils", "customSort"));
                    $result[$packageCode] = $res;
                }
            }
        }

        //uasort($result, array("R3gis\AppBundle\Ckan\CkanUtils", "customSort"));
        usort($result, array("R3gis\AppBundle\Ckan\CkanUtils", "customSort"));
        // print_r($result); die();
        return $result;
    }

    public function getPackageDataFromPackageAndId($package, $id) {
        $data = $this->sanitarizePackageList(array($package));

        $result = null;
        if (!empty($data[0]['resources'])) {
            foreach ($data[0]['resources'] as $val) {
                if ($val['id'] == $id) {
                    $result = $val;
                    break;
                }
            }
        }
        if (empty($result)) {
            throw new \Exception("Can't find packages|id \"{$package}\"|\"{$id}\"");
        }

        return $result;
    }

    public function getDataFormat($package, $id) {
        $data = $this->getPackageDataFromPackageAndId($package, $id);
        $ext = strtolower(strrchr($data['url'], '.'));

        return array($data['format'], $ext == '.zip');
    }

    public function downloadData($package, $id, $destFile) {
        $client = new Client();

        $data = $this->getPackageDataFromPackageAndId($package, $id);
        try {
            $this->log(\Psr\Log\LogLevel::DEBUG, __METHOD__ . ": Download {$data['url']}");
            $response = $this->httpClient->get($data['url']);
            $data = $response->getBody();
            file_put_contents($destFile, $data);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new ApiException(ApiException::SERVER_ERROR, "Download error", array($data['url'], $e->getCode(), $e->getMessage()));
        }
    }

}

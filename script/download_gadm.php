<?php

$configFilename = __DIR__ . '/gadm.txt';
$downloadPath = __DIR__ . '/../gadm-data/';
$baseUrl = 'http://biogeo.ucdavis.edu/data/gadm2/shp/';
// $baseUrl = 'http://data.biogeo.ucdavis.edu/data/gadm2/shp/';

$opts = array('http' =>
  array(
    'method'  => 'GET',
    'timeout' => 10*60  // Very looong timeout
  )
);
$context  = stream_context_create($opts);

if (!file_exists($configFilename)) {
    die("Configuration file \"{$configFilename}\" not found");
}

if (!file_exists($downloadPath)) {
    if (!mkdir( $downloadPath ) ) {
        die("Error creating directory \"{$downloadPath}\"");
    }
}

$lines = file($configFilename);
$codeList = array();
foreach($lines as $line) {
    $line = trim($line);
    if ($line == '' || in_array($line[0], array('#', ';'))) {
        continue;
    }
    if (( $p = strpos($line, ' ')) === false) {
        continue;
    }
    $code = substr($line, 0, $p);
    $name = trim(substr($line, $p+1));
    $codeList[$code] = $name;
}

$tot = count($codeList);
$count = 0;
foreach($codeList as $code=>$name) {
    $count++;
    $fileName = "{$code}_adm.zip";
    $destFileName = "{$downloadPath}{$fileName}";
    $srcUrl = "{$baseUrl}{$fileName}";
    
    if (file_exists($destFileName)) {
        printf("%-10s %-20s [%3d/%3d]\n", 'Skip', $name, $count, $tot);
    } else {
        printf("%-10s %-20s [%3d/%3d]\n", 'Download', $name, $count, $tot);
        $data = file_get_contents($srcUrl, false, $context);
        if (strlen($data) > 0) {
            file_put_contents($destFileName, $data);
        }    
    }
}

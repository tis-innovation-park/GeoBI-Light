<?php

if (empty($argv[1])) {
    echo "Missing configuration template file";
    exit(1);
}
if (empty($argv[2])) {
    echo "Missing configuration destination file";
    exit(1);
}

$templateFile = $argv[1];
$destFile = $argv[2];
$cfgFileName = realpath(__DIR__ . '/../../app/config/parameters.yml');
$authorPath = realpath(__DIR__ . '/../../author') . '/';

if (!file_exists($templateFile)) {
    echo "File {$templateFile} not found";
    exit(1);
}

if (!file_exists($cfgFileName)) {
    echo "File {$cfgFileName} not found";
    exit(1);
}

if (!is_dir($authorPath)) {
    echo "Directory {$authorPath} not found";
    exit(1);
}

$parameters = array();
foreach(file($cfgFileName) as $line) {
    if ( ($p = strpos($line, ':')) !== false) {
        $key = trim(substr($line, 0, $p));
        $val = trim(substr($line, $p+1));
        if (!empty($val)) {
            if ($val[0] == "'" && $val[strlen($val)-1] == "'") {
                $val = trim(substr($val, 1, -1));
            }
            $parameters[$key] = $val == 'null' ? null : $val;
        }    
    }
}

$parameters['author_path'] = $authorPath;

if (empty($parameters['database_driver'])) {
    echo "Missing database_parameter in {$cfgFileName}\n";
    exit(1);
}

if ($parameters['database_driver'] != 'pdo_pgsql') {
    echo "Invalid value for parameter database_driver ({$parameters['database_driver']}. Must be pdo_pgsql)\n";
    exit(1);
}


$content = file_get_contents($templateFile);

foreach($parameters as $key=>$val) {
    $search = "[" . strtoupper($key) . "]";
    $content = str_replace($search, $val, $content);
}

echo "{$templateFile} => {$destFile}\n";
file_put_contents($destFile, $content);


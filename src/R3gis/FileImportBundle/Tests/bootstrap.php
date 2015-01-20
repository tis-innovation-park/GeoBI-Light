<?php

function R3gis_fileimportbundle_autoload($class)
{
    
    if (0 === strpos($class, 'R3gis\\FileImportBundle\\')) {
        $path = implode('/', array_slice(explode('\\', $class), 3)).'.php';
        
        require_once __DIR__.'/../'.$path;
        return true;
    }
}

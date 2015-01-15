<?php

namespace R3gis\AppBundle\Utils;


final class DefaultsUtils 
{

    static public function getMapName($lang) 
    {
        return 'Untitled';
    }
    
    static public function getMapDescription($lang) 
    {
        return 'Description';
    }
    
    static public function getMapBackgroundType($lang) 
    {
        return 'osm';
    }
    
    static public function getMapLayerName($lang) 
    {
        return 'Untitled';
    }
    
    static public function getMapLayerNoDataColor($lang) 
    {
        return '#F18F18';
    }
    
    static public function getMapLayerOutlineColor($lang) 
    {
        return '#98989C';
    }
    
    static public function getMapLayerStartColor($lang) 
    {
        return '#ff0000';
    }
    
    static public function getMapLayerEndColor($lang) 
    {
        return '#0000ff';
    }
    
    static public function getMapLayerOpacity($lang) 
    {
        return 80;
    }
    
    static public function getMapLayerDivision($lang) 
    {
        return 4;
    }
    
    static public function getMapLayerType($lang) 
    {
        return 1; // 1->FILL @ TODO Take it from database
    }
    
    static public function getMapLayerDivisionType($lang) 
    {
        return 4; // 4->QUANTILE @ TODO Take it from database
    }
    
    static public function getMapLayerMinSize($lang) 
    {
        return 4;
    }
    
    static public function getMapLayerMaxSize($lang) 
    {
        return 20;
    }
    
    static public function getMapLayerSizeType($lang) 
    {
        return 'fixed';
    }
    
    static public function getMapLayerSymbol($lang) 
    {
        return 'circle';
    }
    
    
}

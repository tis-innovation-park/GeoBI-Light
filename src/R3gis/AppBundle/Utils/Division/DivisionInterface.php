<?php

namespace R3gis\AppBundle\Utils\Division;

interface DivisionInterface
{
    /**
     * Return the limits for the given data and the classNr calculated with the natural algorithm (
     * @param array $data                  the data
     * @param integer $classNr             the number of the class
     * @param integer $precision           number of decimal digit
     * @param bool $removeEmptyData        if true empty data are removed
     * @return array                       the limit ($classNr - 1 values returned)
    */
    public function getLimits(array $data, $classNr, $precision, $removeEmptyData=true);
    
}
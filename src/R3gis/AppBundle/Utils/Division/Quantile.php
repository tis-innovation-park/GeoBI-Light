<?php

namespace R3gis\AppBundle\Utils\Division;

use R3gis\AppBundle\Utils\Division\DivisionInterface;

class Quantile implements DivisionInterface {

    public function getLimits(array $data, $classNr, $precision, $removeEmptyData = true) {
        $result = array();
        $tmp = array();

        if ($removeEmptyData) {
            foreach ($data as $val) {
                if ($val <> '') {
                    $tmp[] = $val;
                }
            }
        } else {
            $tmp = $data;
        }

        sort($tmp);
        $classNr = min(count($tmp), $classNr);
        $oldVal = null;
        for ($cont = 1; $cont < $classNr; $cont++) {
            $i = ceil((count($tmp) / $classNr) * $cont);
            if ($precision === null) {
                $val = $tmp[$i - 1];
            } else {
                $val = round($tmp[$i - 1], $precision);
            }
            if ($val !== $oldVal) {
                $result[] = $oldVal = $val;
            }
        }
        return $result;
    }

}

<?php

namespace R3gis\AppBundle\Utils\Division;

use R3gis\AppBundle\Utils\Division\DivisionInterface;

class Natural implements DivisionInterface {

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
        if (count($tmp) == 0) {
            return array();
        }
        sort($tmp);
        $classNr = min(count($tmp), $classNr);
        $delta = count($tmp) / $classNr;
        $pos = $delta;
        $oldVal = null;
        for ($cont = 1; $cont < $classNr; $cont++) {
            if ($precision === null) {
                $val = $tmp[round($pos) - 1];
            } else {
                $val = round($tmp[round($pos) - 1], $precision);
            }
            if ($val !== $oldVal) {
                $result[] = $oldVal = $val;
            }
            $pos = $pos + $delta;
        }
        return $result;
    }

}

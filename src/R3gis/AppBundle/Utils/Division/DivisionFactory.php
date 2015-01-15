<?php

namespace R3gis\AppBundle\Utils\Division;

# use R3gis\AppBundle\Utils\Division\DivisionInterface;

class DivisionFactory {

    static public function get($type) {
        switch ($type) {
            case 'manual':
            case 'natural':
                $instance = new Natural();
                break;
            case 'quantile':
                $instance = new Quantile();
                break;
            case 'quantile-round':
                $instance = new QuantileRound();
                break;
            default:
                throw new \Exception("Unknown division type\"{$type}\"");
        }
        return $instance;
    }

    static public function unmapDivision($id) {
        $arrayMap = array(1 => 'manual', 2 => 'natural', 3 => 'qualtile', 4 => 'quantile-round');
        if (!array_key_exists($id, $arrayMap)) {
            throw new \Exception("Unknown division id\"{$type}\"");
        }
        return $arrayMap[$id];
    }

}

<?php

namespace R3gis\AppBundle\Utils\Division;

use R3gis\AppBundle\Utils\Division\DivisionInterface;

class QuantileRound implements DivisionInterface {

    private function getMaxDecimals(array $data) {
        $dec = 0;
        foreach ($data as $val) {
            $val = str_replace(',', '.', $val);
            $pos = strpos($val, '.');
            if ($pos !== false) {
                $dec = max($dec, strlen($val) - $pos - 1);
            }
        }
        return $dec;
    }

    private function arrayMultiply(array $data, $multiplier, $round = null) {
        foreach ($data as $key => $val) {
            if ($round !== null) {
                $data[$key] = round($val * $multiplier, $round);
            } else {
                $data[$key] = $val * $multiplier;
            }
        }
        return $data;
    }

    private function findNextRoundElement($data, $pos) {
        while (isset($data[$pos + 1]) && $data[$pos] == $data[$pos + 1]) {
            $pos++;
        }
        if (empty($data[$pos + 1])) {
            return null;
        } else {
            return $data[$pos + 1];
        }
    }

    public function getLimits(array $data, $classNr, $precision, $removeEmptyData = true) {
        $niceNumbers = array(1, 5, 10, 25, 50, 100, 250, 500, 1000, 5000, 10000, 50000, 100000, 500000, 1000000, 5000000, 10000000, 50000000, 100000000);

        if ($precision === null) {
            // Moltiplico i dati per trasformarli in intero
            $calculatedDecimals = min($this->getMaxDecimals($data), 5);
            $data = $this->arrayMultiply($data, pow(10, $calculatedDecimals), 0);
        } else {
            $data = $this->arrayMultiply($data, pow(10, $precision), 0);
        }
        $result = array();
        $data2 = array();
        if ($removeEmptyData) {
            foreach ($data as $val) {
                if ($val <> '') {
                    $data2[] = $val;
                }
            }
        } else {
            $data2 = $data;
        }
        sort($data2);
        $classNr = min(count($data2), $classNr);
        $oldVal = null;
        for ($cont = 1; $cont < $classNr; $cont++) {
            $i = ceil((count($data2) / $classNr) * $cont);
            if ($precision === null) {
                $val = $data2[$i - 1];
            } else {
                $val = round($data2[$i - 1], $precision);
            }
            if ($val !== $oldVal) {
                $result[] = $oldVal = $val;
            }
        }
        // print_r($result);
        // Cerco di arrotondare al numero "più bello"
        $limits = array();
        $newLimits = array();
        foreach ($result as $quantile) {
            $pos = array_search($quantile, $data2);
            $next = $this->findNextRoundElement($data2, $pos); // SS Verificare esistenza

            $val = $quantile;
            $step = 1;
            $nice = 0;
            $found = array();
            while ($val <= $next) {
                foreach ($niceNumbers as $nicePos => $nr) {
                    if (floor($val) % $nr == 0) {
                        if (!isset($found[$nicePos])) {
                            $nice = $nicePos;
                            $found[$nicePos] = $val;
                        }
                    } else {
                        break;
                    }
                }
                $val += $niceNumbers[$nice];
            }
            if (count($found) == 1) {
                $newLimits[] = $quantile;
            } else {
                $newLimits[] = array_pop($found);
            }
        }
        // Verifica applicabilità dei limiti (Sepre crescenti). Se fallisce restituisce il valore vecchio
        for ($i = 1; $i < count($newLimits); $i++) {
            if ($newLimits[$i] <= $newLimits[$i - 1]) {
                // Ridivido i dati precedentemente moltiplicati
                if ($precision === null) {
                    $result = $this->arrayMultiply($result, 1 / pow(10, $calculatedDecimals));
                } else {
                    $result = $this->arrayMultiply($result, 1 / pow(10, $precision));
                }
                return $result;
            }
        }
        // Ridivido i dati precedentemente moltiplicati
        if ($precision === null) {
            $newLimits = $this->arrayMultiply($newLimits, 1 / pow(10, $calculatedDecimals));
        } else {
            $newLimits = $this->arrayMultiply($newLimits, 1 / pow(10, $precision));
        }
        // print_r($newLimits);
        return $newLimits;
    }

}

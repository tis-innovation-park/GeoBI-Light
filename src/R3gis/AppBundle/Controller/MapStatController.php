<?php

namespace R3gis\AppBundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @Route("/map/stat")
 */
class MapStatController extends Controller {

    /**
     * @Route("/{hash}", methods = {"GET"}, name="r3gis.map.check")
     */
    public function checkAction(Request $request, $hash) {

        $em = $this->getDoctrine()->getManager();
        $map = $this->getDoctrine()->getRepository('R3gisAppBundle:Map')->findOneByHash($hash);
        if (empty($map)) {
            $response = new Response('Not Found', 404, array('content-type' => 'text/plain'));
        } else {
            $response = new Response('Found', 200, array('content-type' => 'text/plain'));
        }
        return $response;
    }

    /**
     * Convert the SHAPE geometry type to MapServer geometry type
     * @param string $geometryType
     */
    private function getMSGeometryType($geometryType) {

        if ($geometryType == 'point') {
            return MS_LAYER_POINT;
        }
        $convesionArray = array(
            'POINT' => MS_LAYER_POINT,
            'MULTIPOINT' => MS_LAYER_POINT,
            'LINESTRING' => MS_LAYER_LINE,
            'MULTILINESTRING' => MS_LAYER_LINE,
            'POLYGON' => MS_LAYER_POLYGON,
            'MULTIPOLYGON' => MS_LAYER_POLYGON);
        if (!array_key_exists($geometryType, $convesionArray)) {
            throw New \Exception("Unsupported geometry type  \"{$geometryType}\"");
        }
        return $convesionArray[$geometryType];
    }

    private function getMapLayerInfo($hash, $order) {

        $db = $this->getDoctrine()->getManager()->getConnection();
        $sql = "SELECT ml_id AS id, ml_order AS order, lang_id AS lang, lt_code AS layer_type, ml_schema AS schema, ml_table AS table, ml_is_shape AS is_shape, 
                       f_geometry_column AS geometry_column, type AS geomery_type, srid, ml_data_column AS data_column, 
                       at.at_id AS area_type_id, LOWER(at_code) AS area_type_code, ml_opacity AS opacity,
                       ml_nodata_color AS nodata_color, ml_outline_color AS outline_color, 
                       ml_symbol AS symbol, ml_min_size AS min_size, ml_max_size AS max_size, ml_size_type AS size_type
                FROM geobi.map m
                INNER JOIN geobi.map_layer l ON m.map_id=l.map_id
                INNER JOIN geobi.layer_type lt ON l.lt_id=lt.lt_id
                LEFT JOIN data.area_type at ON l.at_id=at.at_id
                LEFT JOIN public.geometry_columns g ON ml_is_shape IS TRUE AND f_table_schema=ml_schema and f_table_name=ml_table and f_geometry_column='the_geom'
                WHERE map_hash=:map_hash AND ml_order=:ml_order";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('map_hash' => $hash, 'ml_order' => $order));
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            if (!$data['is_shape']) {
                $data['geomery_type'] = 'MULTIPOLYGON';
                $data['geometry_column'] = 'the_geom';
                $data['srid'] = 3857;
            }
            $sql = "SELECT mc_name AS name, mc_order AS order, mc_number AS number, mc_text AS text, mc_color AS color
                    FROM geobi.map_class 
                    WHERE ml_id={$data['id']}
                    ORDER BY mc_order";
            $class = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($class)) {
                $data['class'] = $class;
            } else {
                $data['class'] = array();
            }
        }

        return $data;
    }

    private function RGB2MSColor($rgbColor) {
        if (!empty($rgbColor)) {
            $rgbColor = str_replace('#', '', $rgbColor);
            $r = hexdec(substr($rgbColor, 0, 2));
            $g = hexdec(substr($rgbColor, 2, 2));
            $b = hexdec(substr($rgbColor, 4, 2));
            return "{$r} {$g} {$b}";
        } else {
            return null;
        }
    }

    // Return array of string
    private function getPointStyleText($color, $outlineColor, $symbol, $size, $opacity) {
        $textPart = array();
        if (empty($size)) {
            $size = 80;
        }
        if (!empty($outlineColor)) {
            $size-=2;
        }
        $outlineSize = $size + 2;
        $outlineOpacity = $opacity; //min(100, $opacity + 30); 
        $symbol = strtoupper($symbol);


        if (!empty($outlineColor)) {
            $textPart[] = "  STYLE";
            $msColor = $this->RGB2MSColor($outlineColor);
            $textPart[] = "    SYMBOL \"{$symbol}\"";
            $textPart[] = "    COLOR {$msColor}";
            $textPart[] = "    SIZE {$outlineSize}";
            $textPart[] = "    OPACITY {$opacity}";
            $textPart[] = '  END';
        }

        if (!empty($color)) {
            $msColor = $this->RGB2MSColor($color);
            $textPart[] = "  STYLE";
            $textPart[] = "    SYMBOL \"{$symbol}\"";
            $textPart[] = "    COLOR {$msColor}";
            $textPart[] = "    SIZE {$size}";
            $textPart[] = "    OPACITY {$outlineOpacity}";
            $textPart[] = '  END';
        }
        return $textPart;
    }

    private function getLineStyleText($color, $outlineColor, $size, $opacity) {
        $textPart = array();
        if (empty($size)) {
            $size = 80;
        }
        if (!empty($outlineColor)) {
            $size-=2;
        }
        $outlineSize = $size + 2;
        $outlineOpacity = $opacity; //min(100, $opacity + 30); 
        if (!empty($color)) {
            $msColor = $this->RGB2MSColor($color);
            $textPart[] = "  STYLE";
            $textPart[] = "    COLOR {$msColor}";
            $textPart[] = "    SIZE {$outlineSize}";
            $textPart[] = "    WIDTH {$outlineSize}";
            $textPart[] = "    OPACITY {$outlineOpacity}";
            $textPart[] = '  END';
        }

        if (!empty($outlineColor)) {
            $textPart[] = "  STYLE";
            $msColor = $this->RGB2MSColor($outlineColor);
            $textPart[] = "    COLOR {$msColor}";
            $textPart[] = "    SIZE {$size}";
            $textPart[] = "    WIDTH {$size}";
            $textPart[] = "    OPACITY {$opacity}";
            $textPart[] = '  END';
        }
        return $textPart;
    }

    private function getPolygonStyleText($color, $outlineColor, $size, $opacity) {
        $textPart = array();

        $outlineOpacity = $opacity; //min(100, $opacity + 30); 
        if (!empty($color)) {
            $textPart[] = "  STYLE";
            $msColor = $this->RGB2MSColor($color);
            $textPart[] = "    COLOR {$msColor}";
            $textPart[] = "    OPACITY {$opacity}";
            $textPart[] = '  END';
        }

        if (!empty($outlineColor)) {
            $msColor = $this->RGB2MSColor($outlineColor);
            $textPart[] = "  STYLE";
            $textPart[] = "    OUTLINECOLOR {$msColor}";
            $textPart[] = "    OPACITY {$outlineOpacity}";
            if ($size) {
                $textPart[] = "    WIDTH {$size}";
            }
            $textPart[] = '  END';
        }
        return $textPart;
    }

    private function addClass($layer, $layerType, $expressionText, array $opt) {
        $opt = array_merge(array('color' => null, 'outline_color' => null, 'opacity' => null, 'symbol' => 'circle', 'hightlight' => null), $opt);
        $msLayerType = $this->getMSGeometryType($layerType);

        if ($msLayerType == MS_LAYER_POINT) {
            $size = empty($opt['size']) ? 9 : $opt['size'];
            $symbol = empty($opt['symbol']) ? 'circle' : $opt['symbol'];
            $styleTextPart = $this->getPointStyleText($opt['color'], $opt['outline_color'], $symbol, $size, $opt['opacity']);
        } else if ($msLayerType == MS_LAYER_LINE) {
            $styleTextPart = $this->getLineStyleText($opt['color'], $opt['outline_color'], 3, $opt['opacity']);
        } else {
            $size = 1;
            $opacity = $opt['opacity'];
            if (!empty($opt['hightlight'])) {
                $size = 5;
                $opacity = 100;
            }
            $styleTextPart = $this->getPolygonStyleText($opt['color'], $opt['outline_color'], $size, $opacity);
        }
        $class = ms_newClassObj($layer);
        $textPart = array();
        $textPart[] = 'CLASS';
        if (!empty($expressionText)) {
            $textPart[] = "  EXPRESSION ({$expressionText})";
        }
        $textPart = array_merge($textPart, $styleTextPart);

        $textPart[] = 'END';
        $class->updateFromString(implode("\n", $textPart));
    }

    private function addNoDataClass($layer, $layerType, array $opt) {
        $this->addClass($layer, $layerType, null, $opt);
    }

    private function addChartClass($layer, $dataColumn, $expressionText, array $opt) {
        $opt = array_merge(array('color' => null, 'outline_color' => null, 'opacity' => null, 'offset' => 0), $opt);

        $class = ms_newClassObj($layer);
        $msColor = $this->RGB2MSColor($opt['color']);
        $msOutlineColor = $this->RGB2MSColor($opt['outline_color']);
        $textPart = array();
        $textPart[] = "CLASS";
        if (!empty($expressionText)) {
            $textPart[] = "  EXPRESSION ({$expressionText})";
        }
        $textPart[] = "  STYLE";
        $textPart[] = "    COLOR {$msColor}";
        $textPart[] = "    OUTLINECOLOR {$msOutlineColor}";
        $textPart[] = "    SIZE [{$dataColumn}]";
        $textPart[] = "    OPACITY {$opt['opacity']}";
        $textPart[] = "  END";
        $textPart[] = "END";
        $class->updateFromString(implode("\n", $textPart));
    }

    private function getNumberExpression($field, $val1, $val2) {
        if ($val1 === null && $val2 === null) {
            return null;
        } else if ($val1 === null) {
            return "[{$field}] <= {$val2}";
        } else if ($val2 === null) {
            return "[{$field}] > {$val1}";
        } else if ($val1 == $val2) {
            return "[{$field}] = {$val1}";
        } else {
            return "([{$field}] > {$val1}) && ([{$field}] <= {$val2})";
        }
    }

    /**
     * @Route("/{hash}/stat/{order}", methods = {"GET"}, name="r3gis.map.wms_wrapper")
     */
    public function statWmsAction(Request $request, $hash, $order) {
        $kernel = $this->get('kernel');
        $mapfile = $kernel->locateResource('@R3gisAppBundle/Resources/mapfile/stat.map');

        $fs = new Filesystem();
        if (!$fs->exists($mapfile)) {
            throw New \Exception("Mapfile \"{$mapfile}\" not found");
        }

        $mapInfo = $this->getMapLayerInfo($hash, $order);
        $highlight = (int) $request->query->get('HIGHLIGHT');

        if (!empty($mapInfo)) {

            // Generate connection string
            $dbPost = $this->container->getParameter('database_port');
            $connectionString = sprintf("host=%s dbname=%s user=%s password=%s port=%s", $this->container->getParameter('database_host'), $this->container->getParameter('database_name'), $this->container->getParameter('database_user'), $this->container->getParameter('database_password'), empty($dbPost) ? 5432 : $dbPost);

            $this->map = ms_newMapobj($mapfile);

            $objRequest = $this->setupRequest($request);

            $layername = 'stat';
            $layer = $this->map->getLayerByName($layername);
            if (empty($layer)) {
                throw New \Exception("Layer \"{$layername}\" not found on mapfile \"{$mapfile}\"");
            }
            $layer->set('connection', $connectionString);

            $fields = array();
            $fields[] = 'gid';
            $mapInfo['ms_layer_type'] = $mapInfo['layer_type'];
            $mapInfo['ms_geomery_type'] = $mapInfo['geomery_type'];

            // Fallback (no data column)
            if (($mapInfo['layer_type'] == 'pie' || $mapInfo['layer_type'] == 'bar') && empty($mapInfo['data_column'])) {
                $mapInfo['layer_type'] = 'point';
            }

            if ($mapInfo['layer_type'] == 'point') {
                $fields[] = 'ST_PointOnSurface(the_geom) AS the_geom';
                $mapInfo['ms_layer_type'] = 'point';
                $mapInfo['ms_geomery_type'] = 'point';
            } else if ($mapInfo['layer_type'] == 'pie' || $mapInfo['layer_type'] == 'bar') {
                $fields[] = 'the_geom';
                $db = $this->getDoctrine()->getManager()->getConnection();
                $sum = $db->query("SELECT COALESCE(SUM({$mapInfo['data_column']}), 0) FROM {$mapInfo['schema']}.{$mapInfo['table']}")->fetchColumn();

                $fields[] = "({$sum}) AS {$mapInfo['data_column']}_total";
            } else {
                if ($highlight) {
                    $fields[] = 'st_buffer(the_geom, 0) AS the_geom';
                } else {
                    $fields[] = 'the_geom';
                }
            }
            if (!empty($mapInfo['data_column'])) {
                $fields[] = $mapInfo['data_column'];
            }
            $fieldText = implode(', ', $fields);
            if ($mapInfo['is_shape']) {
                $sql = "SELECT {$fieldText} FROM {$mapInfo['schema']}.{$mapInfo['table']}";
                $layer->set('type', $this->getMSGeometryType($mapInfo['ms_geomery_type']));
            } else {
                $geoTableName = empty($mapInfo['area_type_code']) ? 'area' : "area_part_{$mapInfo['area_type_code']}";
                $sql = "SELECT {$fieldText}" .
                        " FROM {$mapInfo['schema']}.{$mapInfo['table']} " .
                        "INNER JOIN data.{$geoTableName} USING (ar_id) ";
                if ($highlight) {
                    $sql .= "WHERE gid={$highlight}";
                }

                $layer->set('type', MS_LAYER_POLYGON);
            }

            if ($mapInfo['layer_type'] == 'point') {
                $layer->set('type', MS_LAYER_POINT);
            }

            if ($mapInfo['layer_type'] == 'pie') {
                $layer->set('type', MS_LAYER_CHART);
                $layer->setprocessing("CHART_SIZE={$mapInfo['min_size']}");
                $layer->setprocessing("CHART_TYPE=pie");
            }

            if ($mapInfo['layer_type'] == 'bar') {
                $layer->set('type', MS_LAYER_CHART);
                $w = $mapInfo['min_size'] / 2;
                $h = $mapInfo['min_size'];
                $layer->setprocessing("CHART_SIZE={$w} {$h}");
                $layer->setprocessing("CHART_TYPE=bar");
            }
            
            $layer->set('data', "the_geom FROM ({$sql}) AS foo USING UNIQUE gid USING SRID={$mapInfo['srid']}");

            $tot = count($mapInfo['class']);
            $lastValue = null;
            $i = 0;

            // Variable point size
            if ($mapInfo['ms_layer_type'] == 'pie' || $mapInfo['ms_layer_type'] == 'bar') {
                if ($mapInfo['class'][0]['number'] > 0) {
                    $layer->setFilter("[{$mapInfo['data_column']} > {$mapInfo['class'][0]['number']}]");
                }
                foreach ($mapInfo['class'] as $class) {
                    /* $i++;
                      if ($i < $tot) {
                      $expression = $this->getNumberExpression($mapInfo['data_column'], $lastValue, $class['number']);
                      $lastValue = $class['number'];
                      } else {
                      $expression = $this->getNumberExpression($mapInfo['data_column'], $lastValue, null);
                      } */
                    $expression = null;
                    $this->addChartClass($layer, $mapInfo['data_column'], $expression, array(
                        'color' => $class['color'],
                        'outline_color' => $mapInfo['outline_color'],
                        'opacity' => $mapInfo['opacity'],
                        'offset' => $mapInfo['order'] * $mapInfo['min_size']));
                    break;
                }
                $opacity = $mapInfo['opacity'];
                if ($mapInfo['layer_type'] == 'bar') {
                    $opacity = 0;
                }
                $this->addChartClass($layer, "{$mapInfo['data_column']}_total", null, array(
                    'color' => $mapInfo['nodata_color'],
                    'outline_color' => $mapInfo['outline_color'],
                    'opacity' => $opacity));
            } else if ($mapInfo['ms_layer_type'] == 'point' && $mapInfo['size_type'] == 'variable') {
                
                    $totClasses = count($mapInfo['class']);
                    $size = $mapInfo['min_size'];
                    $delta = abs($mapInfo['max_size'] - $mapInfo['min_size']) / ($totClasses - 1);
                    
                    $color = $mapInfo['nodata_color'];
                    $outlineColor = $mapInfo['outline_color'];
                    $opacity = $mapInfo['opacity'];
                    if ($highlight) {
                        $color = null;
                        $outlineColor = 'FFFF00';
                        $opacity = 100;
                        $size += 3;
                    }
                    
                    foreach ($mapInfo['class'] as $class) {
                        $i++;
                        if ($i < $tot) {
                            $expression = $this->getNumberExpression($mapInfo['data_column'], $lastValue, $class['number']);
                            $lastValue = $class['number'];
                        } else {
                            $expression = $this->getNumberExpression($mapInfo['data_column'], $lastValue, null);
                        }
                        $this->addClass($layer, $mapInfo['ms_geomery_type'], $expression, array(
                            'color' => $color,
                            'outline_color' => $outlineColor,
                            'opacity' => $opacity,
                            'symbol' => $mapInfo['symbol'],
                            'size' => $size));
                        $size = round($size + $delta);
                    }
            } else {
                if (!$highlight) {
                    foreach ($mapInfo['class'] as $class) {
                        $i++;
                        if ($i < $tot) {
                            $expression = $this->getNumberExpression($mapInfo['data_column'], $lastValue, $class['number']);
                            $lastValue = $class['number'];
                        } else {
                            $expression = $this->getNumberExpression($mapInfo['data_column'], $lastValue, null);
                        }
                        $this->addClass($layer, $mapInfo['ms_geomery_type'], $expression, array(
                            'color' => $class['color'],
                            'outline_color' => $mapInfo['outline_color'],
                            'opacity' => $mapInfo['opacity'],
                            'symbol' => $mapInfo['symbol'],
                            'size' => $mapInfo['min_size']));
                    }
                    $this->addNoDataClass($layer, $mapInfo['ms_geomery_type'], array(
                        'color' => $mapInfo['nodata_color'],
                        'outline_color' => $mapInfo['outline_color'],
                        'opacity' => $mapInfo['opacity'],
                        'symbol' => $mapInfo['symbol']
                    ));
                } else {
                    $opacity = $mapInfo['opacity'];
                    $opacity = 60;
                    $color = 'ffff00';
                    $this->addNoDataClass($layer, $mapInfo['ms_geomery_type'], array(
                        'color' => $color,
                        'outline_color' => null, //'FFFF00',
                        'opacity' => $opacity,
                        'symbol' => $mapInfo['symbol'],
                        'hightlight' => $highlight
                    ));
                }
            }


            /* Enable output buffer */
            ms_ioinstallstdouttobuffer();

            /* Eexecute request */
            $this->map->owsdispatch($objRequest);
            // $this->map->save('/tmp/geobi.map');


            $contenttype = ms_iostripstdoutbuffercontenttype();
            // ONLY IN DEV MODE
            //if (substr($contenttype, 0, 5) == 'image') {
            //    header("Content-type: {$contenttype}");
            //}
            if (empty($_REQUEST['showerror'])) {
              header('Content-type: image/png');
            }
            ms_iogetStdoutBufferBytes();

            ms_ioresethandlers();
        } else {
            // TODO: Draw an empty image
            die('No info');
        }
        die();
    }

    private function setupRequest(Request $request) {

        $presetParams = array(
            'map' => null
        );
        $objRequest = ms_newOwsrequestObj();
        foreach ($this->getRequest()->query->all() as $param => $value) {
            if (!is_string($value)) {
                continue;
            }
            $paramLower = strtolower($param);
            if (isset($presetParams[$paramLower])) {
                if (!empty($presetParams[$paramLower])) {
                    $objRequest->setParameter($param, $presetParams[$paramLower]);
                }
            } else {
                $objRequest->setParameter($param, stripslashes($value));
            }
        }
        return $objRequest;
    }

}

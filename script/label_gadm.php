<?php

$labelFile = __DIR__ . '/label.csv';
$languages = array('it', 'de', 'en');
$dsnArray = array('database'=>'geobi', 'hostspec'=>'127.0.0.1', 'username'=>'geobi', 'password'=>'geobi');

$db = new PDO("pgsql:dbname={$dsnArray['database']};host={$dsnArray['hostspec']}", $dsnArray['username'], $dsnArray['password']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// DUMP to file
/*
$fp = fopen($labelFile, 'w');
$sql = "SELECT level, code, name_it, name_de, name_en, x(the_geom) AS pos_x, y(the_geom) AS pos_y FROM label";
foreach($db->query($sql, PDO::FETCH_ASSOC) as $row) {
    fputcsv($fp, $row);
    
}
fclose($fp);
die();
*/


$line = 0;
$handle = fopen( $labelFile, "r");
while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $line++;
    $num = count($row);
    if ($num < 7) {
        echo "Line {$line} ignored\n";
    }
    // Intersect
    $level = $row[0];
    $x = $row[5];
    $y = $row[6];
    
    $sql = "SELECT ar_name_id
            FROM data.area
            WHERE st_intersects(the_geom, st_transform(st_setsrid(st_geometryfromtext('POINT({$x} {$y})'), 4326), 3857)) AND at_id={$level}";
    $data = $db->query($sql, PDO::FETCH_ASSOC)->fetchAll();
    if (count($data) == 0) {
        echo "Line {$line} has not intersection [{$row[2]}|{$row[3]}|{$row[4]}]\n";
        
    } else if (count($data) > 1) {
        echo "Line {$line} has " . count($data) . " intersections. Only one allowed\n";
    } else {
        $pos = 2;
        foreach($languages as $lang) {
            $label = trim($row[$pos]);
            if (!empty($label)) {
                $sql = "SELECT * FROM geobi.localization WHERE msg_id={$data[0]['ar_name_id']} AND lang_id = '{$lang}'";
                $oldVal = $db->query($sql, PDO::FETCH_ASSOC)->fetch();
                if (empty($oldVal)) {
                    // echo "Adding translation for [{$level}][{$lang}] {$label}\n";
                    $sql = "INSERT INTO geobi.localization (msg_id, lang_id, loc_text) VALUES (:msg_id, :lang_id, :loc_text)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array('msg_id'=>$data[0]['ar_name_id'], 'lang_id'=>$lang, 'loc_text'=>$label));
                }
            }
            $pos++;
        }
    }
}
fclose($handle);

echo "\nDONE!\n\n";
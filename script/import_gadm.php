<?php

$downloadPath = __DIR__ . '/../gadm-data/';
$tmpPath = __DIR__ . '/../gadm-data/tmp/';
$dsnArray = array('database'=>'geobi', 'hostspec'=>'127.0.0.1', 'username'=>'geobi', 'password'=>'geobi');

$db = new PDO("pgsql:dbname={$dsnArray['database']};host={$dsnArray['hostspec']}", $dsnArray['username'], $dsnArray['password']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!file_exists($downloadPath)) {
    die("Download files first");
}
if (!file_exists($tmpPath)) {
    if (!mkdir( $tmpPath ) ) {
        die("Error creating directory \"{$tmpPath}\"");
    }
}
$files = glob("{$downloadPath}*.zip");

function unzip($file, $path) {
    $zip = new ZipArchive;
    $result = array();
    if ($zip->open($file) === TRUE) {
        $zip->extractTo($path);
        $zip->close();
        $result = glob("{$path}*");
    } else {
        die("\nError unzipping \"{$file}\" to \"{$path}\"\n");
    }
    return $result;
}

function removeFiles($files) {
    foreach($files as $file) {
        if (!unlink($file)) {
            die("\nError deleting \"{$file}\"\n");
        }
    }
}

function initTmpTable(PDO $db) {
    $db->exec("DROP TABLE IF EXISTS impexp.gadm");
    
    $sql = "
CREATE TABLE impexp.gadm (
    gid SERIAL,
    file VARCHAR,
    level SMALLINT,
    iso VARCHAR,
    id_0 INTEGER,
    name_0 VARCHAR,
    name_0_iso VARCHAR,
    name_0_iso_2 VARCHAR,
    name_0_local VARCHAR,
    
    id_1 INTEGER,
    name_1 VARCHAR,
    hasc_1 VARCHAR,
    cc_1 VARCHAR,
    type_1 VARCHAR,
    eng_type_1 VARCHAR,
    remarks_1 VARCHAR,
    valid_from VARCHAR,
    valid_to VARCHAR,
    
    id_2 INTEGER,
    name_2 VARCHAR,
    hasc_2 VARCHAR,
    cc_2 VARCHAR,
    remarks_2 VARCHAR,
    
    id_3 INTEGER,
    name_3 VARCHAR,
    hasc_3 VARCHAR,
    type_3 VARCHAR,
    eng_type_3 VARCHAR,
    remarks_3 VARCHAR,
    
    id_4 INTEGER,
    name_4 VARCHAR,
    name_4_var VARCHAR,
    type_4 VARCHAR,
    eng_type_4 VARCHAR,
    remarks_4 VARCHAR,
    
    id_5 INTEGER,
    name_5 VARCHAR,
    type_5 VARCHAR,
    eng_type_5 VARCHAR,
    
    the_geom geometry,
    CONSTRAINT gadm_pkey PRIMARY KEY (gid)
    
)
";
  $db->exec($sql);
}

function parseImportedFile(PDO $db, $name, $part) {
	$destFields = array('objectid', 'iso', 'id_0', 'name_0', 'id_1', 'name_1', 'id_2', 'name_2', 'id_3', 'name_3', 'id_4', 'name_4', 'id_5', 'name_5', 'the_geom');

	$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
	foreach(array('hasc_1', 'cc_1', 'remarks_1', 'validfr_1', 'validto_1', 
	              'hasc_2', 'cc_2', 'remarks_2', 'validfr_2', 'validto_2', 
				  'hasc_3', 'cc_3', 'remarks_3', 'validfr_3', 'validto_3', 
				  'remarks_4', 'validfr_4', 'validto_4', 
				  'validfr_5', 'validto_5') as $f) {
		if (!array_search($f, $tableFields)) {
			$db->exec("ALTER TABLE impexp.gadm_tmp ADD COLUMN {$f} VARCHAR");
		}
	}
	
    
	$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
	if (!in_array('id_0', $tableFields) && in_array('id_o', $tableFields)) {
		// Convert names (o to 0)
		$sqlAlter = "ALTER TABLE impexp.gadm_tmp RENAME COLUMN id_o TO id_0";
		$db->exec($sqlAlter);
		$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
		echo "  Renamed field \"id_o\" to \"id_0\"\n";
	}
	if (!in_array('name_0', $tableFields) && in_array('name_o', $tableFields)) {
		// Convert names (o to 0)
		$sqlAlter = "ALTER TABLE impexp.gadm_tmp RENAME COLUMN name_o TO name_0";
		$db->exec($sqlAlter);
		$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
		echo "  Renamed field \"name_o\" to \"name_0\"\n";
	}
    
	
	if (!in_array('name_0', $tableFields) && in_array('name_iso', $tableFields)) {
		// Convert names (o to 0)
		$sqlAlter = "ALTER TABLE impexp.gadm_tmp ADD COLUMN name_0 VARCHAR";
		$db->exec($sqlAlter);
		$sqlAlter = "UPDATE impexp.gadm_tmp SET name_0=name_iso";
		$db->exec($sqlAlter);
		$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
		//echo "  Added field \"name_0\" from \"name_iso\"\n";
	}
	if ($part == 1 && !in_array('engtype_1', $tableFields)) {
		// Convert names (o to 0)
		$sqlAlter = "ALTER TABLE impexp.gadm_tmp ADD COLUMN engtype_1 VARCHAR";
		$db->exec($sqlAlter);
		$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
		echo "{$file}: Added field \"engtype_1\"\n";
	}
	if ($part == 0 && !in_array('iso2', $tableFields)) {
		// Convert names (o to 0)
		$sqlAlter = "ALTER TABLE impexp.gadm_tmp ADD COLUMN iso2 VARCHAR";
		$db->exec($sqlAlter);
		$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
		echo "  Added field \"iso2\"\n";
	}
	if ($part == 3 && !in_array('type_3', $tableFields)) {
		// Convert names (o to 0)
		$sqlAlter = "ALTER TABLE impexp.gadm_tmp ADD COLUMN type_3 VARCHAR";
		$db->exec($sqlAlter);
		$tableFields = array_keys($db->query("SELECT * FROM impexp.gadm_tmp LIMIT 1")->fetch(PDO::FETCH_ASSOC));
		echo "  Added field \"type_3\"\n";
	}
	
	$db->exec("UPDATE impexp.gadm_tmp SET name_0='' WHERE name_0 IS NULL");
	if ($part == 0) {
		$fields = array('iso', 'id_0'=>array('id_0', 'gadmid'), 
		                'name_0'=>array('name_0'),
						'name_0_iso'=>array('name_iso'), 
						'name_0_iso_2'=>array('iso2'), 
						'name_0_local'=>array('name_local'), 
						'the_geom');
		
    } else if ($part == 1) {
		$fields = array('iso', 'id_0'=>array('id_0'), 
		                'name_0'=>array('name_0'), 
						'id_1'=>array('id_1'), 
						'name_1'=>array('name_1'), 
							'hasc_1'=>array('hasc_1'), 
						'cc_1'=>array('cc_1'), 
						'type_1'=>array('type_1'), 
						'eng_type_1'=>array('engtype_1'), 
						'remarks_1'=>array('remarks_1'), 
						'valid_from'=>array('validfr_1'), 
						'valid_to'=>array('validto_1'), 
						'the_geom');
	} else if ($part == 2) {
		$fields = array('iso', 'id_0'=>array('id_0'), 
		                'name_0'=>array('name_0'), 
						'id_1'=>array('id_1'), 
						'name_1'=>array('name_1'), 
						'id_2'=>array('id_2'), 
						'name_2'=>array('name_2'), 
						'hasc_2'=>array('hasc_2'), 
						'cc_2'=>array('cc_2'), 
						'remarks_2'=>array('remarks_2'), 
						'valid_from'=>array('validfr_2'), 
						'valid_to'=>array('validto_2'), 
						'the_geom');
	} else if ($part == 3) {
		$fields = array('iso', 'id_0'=>array('id_0'), 
		                'name_0'=>array('name_0'), 
						'id_1'=>array('id_1'), 
						'name_1'=>array('name_1'), 
						'id_2'=>array('id_2'), 
						'name_2'=>array('name_2'), 
						'id_3'=>array('id_3'), 
						'name_3'=>array('name_3'), 
						'hasc_3'=>array('hasc_3'), 
						'type_3'=>array('type_3'), 
						'eng_type_3'=>array('engtype_3'), 
						'remarks_3'=>array('remarks_3'), 
						'valid_from'=>array('validfr_3'), 
						'valid_to'=>array('validto_3'), 
						'the_geom');
	} else if ($part == 4) {
		$fields = array('iso', 'id_0'=>array('id_0'), 
		                'name_0'=>array('name_0'), 
						'id_1'=>array('id_1'), 
						'name_1'=>array('name_1'), 
						'id_2'=>array('id_2'), 
						'name_2'=>array('name_2'), 
						'id_3'=>array('id_3'), 
						'name_3'=>array('name_3'), 
						'id_4'=>array('id_4'), 
						'name_4'=>array('name_4'), 
						'name_4_var'=>array('varname_4'), 
						'type_4'=>array('type_4'), 
						'eng_type_4'=>array('engtype_4'), 
						'remarks_4'=>array('remarks_4'), 
						'valid_from'=>array('validfr_4'), 
						'valid_to'=>array('validto_4'), 
						'the_geom');
	} else if ($part == 5) {
		$fields = array('iso', 'id_0'=>array('id_0'), 
		                'name_0'=>array('name_0'), 
						'id_1'=>array('id_1'), 
						'name_1'=>array('name_1'), 
						'id_2'=>array('id_2'), 
						'name_2'=>array('name_2'), 
						'id_3'=>array('id_3'), 
						'name_3'=>array('name_3'), 
						'id_4'=>array('id_4'), 
						'name_4'=>array('name_4'), 
						'id_5'=>array('id_5'), 
						'name_5'=>array('name_5'), 
						'type_5'=>array('type_5'), 
						'eng_type_5'=>array('engtype_5'), 
						'valid_from'=>array('validfr_5'), 
						'valid_to'=>array('validto_5'), 
						'the_geom');
	} else {
		die("UNKNOWN PART {$part}");
    	return;
	}

	$fieldNames = array();
	$fieldSelect = array();
	foreach($fields as $fieldKey=>$fieldVal) {
		if (!is_array($fieldVal)) {
		    $fieldKey = $fieldVal;
			$fieldVal = array($fieldVal);
		}
		$fieldNames[] = $fieldKey;
		$found = false;
		foreach($fieldVal as $val) {
			if (array_search($val, $tableFields) !==false) {
				$fieldSelect[] = $val;
				$found = true;
				break;
			}
		}
		if (!$found) {
			die("\n{$val} NOT FOUND in {$baseName}\n");
		}
	}
	$fileName = basename($name);
	$sql = "INSERT INTO impexp.gadm(file, level, " . implode(', ', $fieldNames) . ")
	        SELECT '{$fileName}', {$part}, " . implode(', ', $fieldSelect) . " FROM impexp.gadm_tmp";
	try {
		$db->exec($sql);
	} catch(Exception $e) {
		echo $e->getMessage() . "\nSQL={$sql}\n";
	}	
}


function addI18nText(PDO $db, $textArray) {
	
	$msgId = null;
	if (!is_array($textArray)) {
		$textArray = array('en'=>$textArray);
	}
	foreach($textArray as $lang=>$text) {
		if ($text <> '') {
			if ($msgId === null) {
				$msgId = $db->query("SELECT NEXTVAL('geobi.message_msg_id_seq')")->fetchColumn();
				$hasMessageId = true;
			}
			if ($text <> '') {
				$sql = "INSERT INTO geobi.localization (msg_id, lang_id, loc_text) VALUES ({$msgId}, '{$lang}', TRIM(" . $db->quote($text) . "))";
				$db->exec($sql);
			}
        }
    }
	return $msgId;
}

function getAreaTypeIdByCode(PDO $db, $code) {
    $sql = "SELECT at_id FROM data.area_type WHERE at_code=" . $db->quote($code);
    return $db->query($sql)->fetchColumn();
}

function generateCode($row, $level) {
	switch($level) {
		case 0: return sprintf("00.000.000.%03d", $row['id_0']);
		case 1: return sprintf("00.000.000.%03d.000.%04d", $row['id_0'], $row['id_1']);
		case 2: return sprintf("00.000.000.%03d.000.%04d.%05d", $row['id_0'], $row['id_1'], $row['id_2']);
		case 3: return sprintf("00.000.000.%03d.000.%04d.%05d.%05d", $row['id_0'], $row['id_1'], $row['id_2'], $row['id_3']);
		default:
		  die("generateCode: Invalid level {$level}\n");
	}
}

function codeExists(PDO $db, $ar_code) {
    $sql = "SELECT COUNT(*) FROM data.area WHERE ar_code=" . $db->quote($ar_code);
    return $db->query($sql)->fetchColumn() > 0;
}
    
function addPartition(PDO $db, $areaTypeCode) {
    $code = strtolower($areaTypeCode);
    $tableName = "area_part_{$code}";

    $at_id = getAreaTypeIdByCode($db, $areaTypeCode);
    if ($at_id === null) {
        throw new Exception("Unknown area type \"{$areaTypeCode}\"");
    }
		
    // Create the inherited table
    $sql = "CREATE TABLE data.{$tableName} () INHERITS (data.area)";
    $db->exec($sql);
        
    $sql = "COMMENT ON TABLE data.{$tableName} IS " . $db->quote(sprintf('Partition table for area table (area_type code: %s)', strtolower($areaTypeCode)));
    $db->exec($sql);
        
    // Create the partition check
    $sql = "ALTER TABLE data.{$tableName} ADD CONSTRAINT area_part_{$code}_chk CHECK (at_id={$at_id})";
    $db->exec($sql);

    // Add foreign key
    $sql = "ALTER TABLE data.{$tableName} ADD CONSTRAINT {$tableName}_fk FOREIGN KEY(at_id) REFERENCES data.area_type(at_id) ON DELETE NO ACTION ON UPDATE NO ACTION NOT DEFERRABLE";
    $db->exec($sql);

    // Add unique
    $sql = "ALTER TABLE data.{$tableName} ADD UNIQUE(ar_code)";
    $db->exec($sql);

    // Add index
    $sql = "ALTER TABLE data.{$tableName} ADD CONSTRAINT {$tableName}_pkey PRIMARY KEY(ar_id)";
    $db->exec($sql);
    $sql = "CREATE INDEX {$tableName}_the_geom_gist ON data.{$tableName} USING gist(the_geom)";
    $db->exec($sql);

		
	return $tableName;
}
    

echo "Initializing...\n";

initTmpTable($db);
$tot = count($files);
$count = 0;
foreach($files as $file) {
    $count++;
    printf("%-10s %-20s [%d/%d]\n", 'Import', basename($file), $count, $tot);
    $shpFiles = unzip($file, $tmpPath);
    
    $cleanupFiles = array();
    foreach($shpFiles as $shpFile) {
        $ext = strtolower(strrchr($shpFile, '.'));
        if ($ext == '.shp') {
            // DUMP
            $dmpName = substr($shpFile, 0, -4) . '.sql';
            $part = substr($shpFile, -5, 1);
            $cleanupFiles[] = $dmpName;
            $cmd = "shp2pgsql -s 4326 -c -g the_geom -D -W LATIN1 {$shpFile} impexp.gadm_tmp > {$dmpName}";
            exec("{$cmd} 2>/dev/null", $output, $retVal);
            if ($retVal != 0) {
                die("\nError executing \"{$cmd}\"\n");
            }
            
            $db->exec("DROP TABLE IF EXISTS impexp.gadm_tmp");
            
            // IMPORT
            putenv("PGUSER={$dsnArray['username']}");
            putenv("PGPASSWORD={$dsnArray['password']}");
            
            $cmd = "psql -h {$dsnArray['hostspec']} -d {$dsnArray['database']} -f {$dmpName} --set=ON_ERROR_STOP=1";
            exec("{$cmd} 2>/dev/null", $output, $retVal);
            if ($retVal != 0) {
                die("\nError executing \"{$cmd}\"\n");
            }
            
            $db->beginTransaction();
            $db->exec("SET LOCAL synchronous_commit TO OFF");
            
            parseImportedFile($db, basename( substr($shpFile, 0, -4) ), $part);
              
            $db->exec("DROP TABLE impexp.gadm_tmp");
            $db->commit();
        }
    }
    
    removeFiles($shpFiles);
    removeFiles($cleanupFiles);
}

$sql = "CREATE UNIQUE INDEX gadm_idx ON impexp.gadm USING btree (level, iso, id_0, id_1, id_2, id_3, id_4, id_5)";
$db->exec($sql);

$sql = "VACUUM FULL ANALYZE impexp.gadm";
$db->exec($sql);


echo "Generating data.area structure...\n";

$sql = "DELETE FROM data.area_detail";
$db->exec($sql);

$sql = "DELETE FROM data.area";
$db->exec($sql);

$sql = "DELETE FROM data.area_type";
$db->exec($sql);

$sql = "DELETE FROM geobi.localization";
$db->exec($sql);

$sql = "UPDATE geobi.language SET lang_active=FALSE, lang_order=NULL";
$db->exec($sql);

$sql = "UPDATE geobi.language SET lang_active=TRUE, lang_order=1 WHERE lang_id IN ('it', 'de', 'en')";
$db->exec($sql);


// Reset sequence
$sql = "ALTER SEQUENCE geobi.localization_loc_id_seq RESTART";
$db->exec($sql);
$sql = "ALTER SEQUENCE geobi.message_msg_id_seq RESTART";
$db->exec($sql);
$sql = "ALTER SEQUENCE data.area_type_at_id_seq RESTART";
$db->exec($sql);
$sql = "ALTER SEQUENCE data.area_ar_id_seq RESTART";
$db->exec($sql);

$levelCodeList = array(0=>'NATION', 1=>'REGION', 2=>'PROVINCE', 3=>'MUNICIPALITY');
for($level = 0; $level <= 3; $level++) {
	echo "Importing level {$level} [{$levelCodeList[$level]}]...\n";

	$sql = "DROP TABLE IF EXISTS data.area_part_{$levelCodeList[$level]}";
	$db->exec($sql);
	$msgId = addI18nText($db, $levelCodeList[$level]);
	$sql = "INSERT INTO data.area_type (at_code, at_name_id, at_base_layer) VALUES ('{$levelCodeList[$level]}', {$msgId}, true)";
	$db->exec($sql);

	addPartition($db, $levelCodeList[$level]);
	
	$at_id = getAreaTypeIdByCode($db, $levelCodeList[$level]);
	$sql = "SELECT * FROM impexp.gadm WHERE level={$level} ORDER BY iso, id_0, id_1, id_2, id_3, id_4, id_5, gid";
	$parentCode = null;
	foreach($db->query($sql, PDO::FETCH_ASSOC) as $row) {
        $nameEnglish = $row["name_{$level}"];
        if (preg_match('/^[0-9]+$/', $nameEnglish)) {
            $nameEnglish = '[INVALID]';
        }
        
		$nameLocal = isset($row["name_{$level}_local"]) ? $row["name_{$level}_local"] : $nameEnglish;
		$msgId = addI18nText($db, $nameEnglish);
		if ($msgId == '') {
			echo "  {$ar_code}: missing name (level {$level}).\n";
			//$msgId = 'NULL';
            $msgId = addI18nText($db, '[UNKNOWN]');
		}
		$ar_code = generateCode($row, $level);
		if (codeExists($db, $ar_code)) {
			echo "  Code \"{$ar_code}\" exists. Skipping for {$nameEnglish} / {$nameLocal}\n";
			continue;
		}
		$parentCodeQuoted = $parentCode == '' ? 'NULL' : "'{$parentCode}'";
		
		$localName = $db->quote($nameLocal);
        
        if (in_array($ar_code, array('00.000.000.009'))) {
            echo "  Skipping {$ar_code} ({$localName}) (Reproject error)\n";
            continue;
        }
		$sql = "INSERT INTO data.area (at_id, ar_code, ar_name_id, ar_parent_code, ar_label_priority, the_geom, ar_name_local) 
		        VALUES ({$at_id}, '{$ar_code}', {$msgId}, {$parentCodeQuoted}, {$level}, st_transform(st_multi(st_buffer('{$row['the_geom']}', 0)), 3857), {$localName})";
		try {
			$db->exec($sql);
		} catch(Exception $e) {
			//echo $e->getMessage() . "\nSQL={$sql}\n{$at_id}, '{$ar_code}', {$msgId}, {$parentCodeQuoted}, {$level}, {$localName}";
            echo $e->getMessage() . "\nSQL=...\n{$at_id}, '{$ar_code}', {$msgId}, {$parentCodeQuoted}, {$level}, {$localName}";
			die();
		}	
	}
}


echo "Optimize...\n";
$sql = "VACUUM FULL ANALYZE geobi.localization";
$db->exec($sql);
//echo "  area\n";
$sql = "ALTER TABLE data.area ALTER COLUMN ar_code SET STORAGE PLAIN";
$db->exec($sql);
$sql = "ALTER TABLE data.area ALTER COLUMN ar_parent_code SET STORAGE MAIN";
$db->exec($sql);
$sql = "ALTER TABLE data.area ALTER COLUMN the_geom SET STATISTICS 1000";
$db->exec($sql);
$sql =  "ALTER TABLE data.area SET (fillfactor=100)";
$db->exec($sql);
$sql =  "ALTER INDEX data.area_pkey SET (fillfactor = 100)";
$db->exec($sql);
$sql =  "ALTER INDEX data.area_ar_code_key  SET (fillfactor = 100)";
$db->exec($sql);
$sql = "REINDEX TABLE data.area";
$db->exec($sql);
$sql = "CLUSTER area_the_geom_gist ON data.area";
$db->exec($sql);
$sql = "VACUUM FULL ANALYZE data.area";
$db->exec($sql);
$levelCodeList = array(0=>'NATION', 1=>'REGION', 2=>'PROVINCE', 3=>'MUNICIPALITY');
foreach($levelCodeList as $tbl) {
	$tbl = strtolower($tbl);
	//echo "  {$tbl}\n";
	$sql =  "ALTER TABLE data.area_part_{$tbl} SET (fillfactor=100);";
	$db->exec($sql);
	$sql =  "ALTER INDEX data.area_part_{$tbl}_pkey SET (fillfactor = 100);";
	$db->exec($sql);
	$sql =  "ALTER INDEX data.area_part_{$tbl}_ar_code_key  SET (fillfactor = 100);";
	$db->exec($sql);
	$sql = "REINDEX TABLE data.area_part_{$tbl}";
	$db->exec($sql);
	$sql = "CLUSTER area_part_{$tbl}_the_geom_gist ON data.area_part_{$tbl}";
	$db->exec($sql);
	$sql = "VACUUM FULL ANALYZE data.area_part_{$tbl}";
	$db->exec($sql);
}

echo "\nDONE!\n\n";
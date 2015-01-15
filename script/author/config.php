<?php
/*
GisClient map browser

Copyright (C) 2008 - 2009  Roberto Starnini - Gis & Web S.r.l. -info@gisweb.it

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 3
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/


/************ Session Name ********/
define('GC_SESSION_NAME', 'GEOBI');

ini_set('max_execution_time',90);
ini_set('memory_limit','512M');

error_reporting  (E_ALL & ~E_SCRICT);
ini_set('display_errors', 'On');

define('LONG_EXECUTIONE_TIME',300);
define('LONG_EXECUTION_MEMORY','512M');

define('EXTERNAL_LOGIN_KEY', 'pas0d8ufypasod8fy09872hp4irja.shdfkauyfgo-sdygfo987wyr');

define('TAB_DIR', 'it');


/*******************Installation path *************************/

define('ROOT_PATH','[AUTHOR_PATH]');
define('PUBLIC_URL', '[AUTHOR_URL]');
define('MAP_URL', '[BASE_URL]map/');
define('TILECACHE_CFG','/etc/tilecache.cfg');
define('TILES_CACHE','/tmp/tilecache');
define('IMAGE_PATH','/tmp/');
define('IMAGE_URL','/tmp/');
/*******************                  *************************/

/*******************OWS service url *************************/
define('TILECACHE_URL','[BASE_URL]cgi-bin/tilecache.py'); 
define('GISCLIENT_OWS_URL','[AUTHOR_URL]services/ows.php?'); 
define('GISCLIENT_LOCAL_OWS_URL','[AUTHOR_URL]services/ows.php?'); 
define('MAPSERVER_URL', '[BASE_URL]cgi-bin/mapserv?'); 
/*******************                  *************************/

/**************** PRINT - EXPORT***************/
define('GC_PRINT_TPL_DIR', ROOT_PATH.'public/services/print/');
define('GC_PRINT_TPL_URL', PUBLIC_URL.'services/print/');
define('GC_PRINT_IMAGE_SIZE_INI', ROOT_PATH.'config/print_image_size.ini');
define('GC_WEB_TMP_DIR', ROOT_PATH.'public/services/tmp/');
define('GC_WEB_TMP_URL', PUBLIC_URL.'services/tmp/');
define('GC_PRINT_LOGO_SX', '[BASE_URL]images/logo_sx.gif');
define('GC_PRINT_LOGO_DX', '[BASE_URL]images/logo_dx.gif');
define('R3_FOP_CMD', '/usr/local/fop/fop');
define('GC_FOP_LIB', '[AUTHOR_PATH]lib/r3fop.php');
define('GC_PRINT_SAVE_IMAGE', true);


/******************* TINYOWS **************/
define('TINYOWS_PATH', '/var/www/cgi-bin');
define('TINYOWS_EXEC', 'tinyows');
define('TINYOWS_SCHEMA_DIR', '/usr/share/tinyows/schema/');
define('TINYOWS_ONLINE_RESOURCE', PUBLIC_URL.'services/tinyows/'); // aggiungere ? o & alla fine


/*************  REDLINE ***************/
define('REDLINE_SCHEMA', 'public');
define('REDLINE_TABLE', 'annotazioni');
define('REDLINE_SRID', [SRID]);
define('REDLINE_FONT', 'dejavu-sans');


/****** print vectors ********/
define('PRINT_VECTORS_TABLE', 'print_vectors');
define('PRINT_VECTORS_SRID', [SRID]);


//if (!defined('SKIP_INCLUDE') || SKIP_INCLUDE !== true) {
	require_once (ROOT_PATH."lib/postgres.php");
	require_once (ROOT_PATH."lib/debug.php");
	require_once (ROOT_PATH."config/config.db.php");
	require_once (ROOT_PATH.'lib/gcapp.class.php');
//}

//Author
define('ADMIN_PATH',ROOT_PATH.'public/admin/');

//debug
if(!defined('DEBUG_DIR')) define('DEBUG_DIR',ROOT_PATH.'config/debug/');
if(!defined('DEBUG')) define('DEBUG', 1); // Debugging 0 off 1 on

//if (!defined('SKIP_INCLUDE') || SKIP_INCLUDE !== true) {
	require_once (ROOT_PATH."config/login.php");
//}	

//COSTANTI DEI REPORT
define('MAX_REPORT_ROWS',5000);
define('REPORT_PROJECT_NAME','REPORT');
define('REPORT_MAPSET_NAME','report');
define('FONT_LIST','fonts');
define('MS_VERSION','');

define('CATALOG_EXT','SHP,TIFF,TIF,ECW');//elenco delle estensioni caricabili sul layer
define('DEFAULT_ZOOM_BUFFER',100);//buffer di zoom in metri in caso non venga specificato layer.tolerance
define('MAX_HISTORY',6);//massimo numero di viste memorizzate
define('MAX_OBJ_SELECTED',2000);//massimo numero di oggetti selezionabili
define('WIDTH_SELECTION', 4);//larghezza della polilinea di selezione
define('TRASP_SELECTION', 50);//trasparenza della polilinea di selezione
define('COLOR_SELECTION', '255 0 255');//colore della polilinea di selezione
define('MAP_BG_COLOR', '255 255 255');//colore dello sfondo per default
define('EDIT_BUTTON', 'edit');

define('DEFAULT_TOLERANCE',4);//Raggio di ricerca in caso non venga specificato layer.tolerance
define('LAYER_SELECTION','__sel_layer');//Nome per i layer di selezione
define('LAYER_IMAGELABEL','__image_label');//Nome per il layer testo sulla mappa
define('LAYER_READLINE','__readline_layer');
define('DATALAYER_ALIAS_TABLE','__data__');//nome riservato ad alias per il nome della tabella del layer (usato dal sistema nelle query, non ci devono essere tabelle con questo nome)
define('WRAP_READLINE','\\');
define('COLOR_REDLINE','0 0 255');//Colore Line di contorno oggetti poligono o linea selezionati
define('OBJ_COLOR_SELECTION','255 255 0');//Colore Line di contorno oggetti poligono o linea selezionati
define('MAP_DPI',72);//Mapserver map resolution
define('TILE_SIZE',256);//Mapserver map resolution
define('PDF_K',2);//Mapserver map resolution

define('SCALE','8000000,7000000,6000000,5000000,4000000,3000000,2000000,1000000,900000,800000,700000,600000,500000,400000,300000,200000,100000,50000,25000,10000,7500,5000,2000,1000,500,200,100,50');

//define('OWS_CACHE_TTL', 60); // Map cache (Prevent OL bug for multiple request)
//define('OWS_CACHE_TTL_OPEN', 4*60*60); // Map cache for the 1st open of the map
//define('DYNAMIC_LAYERS', 'g_prati.prati,g_cooperative.data_wiese,g_cooperative.data_wiese'); // comma separated list of dynamic layers (same url different result)


//DATAMANAGER
define('USE_DATA_IMPORT', true);
define('UPLOADED_FILES_PRIVATE_PATH', ROOT_PATH.'files/'); 
define('UPLOADED_FILES_PUBLIC_PATH', ROOT_PATH.'public/services/files/'); 
define('UPLOADED_FILES_PUBLIC_URL', PUBLIC_URL.'services/files/'); 



//Icone della legenda
define('LEGEND_ICON_W',24);
define('LEGEND_ICON_H',16);
define('LEGEND_POINT_SIZE',15);
define('LEGEND_LINE_WIDTH',1);
define('LEGEND_POLYGON_WIDTH',2);
define('PRINT_PDF_FONT','times');


define('CLIENT_LOGO', null);

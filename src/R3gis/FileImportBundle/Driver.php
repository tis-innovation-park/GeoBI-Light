<?php

namespace R3gis\FileImportBundle;

use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;

/**
 * Proposed driver implementation
 * 
 * PH + DD, 18/10/2013
 * __construct()
 * setTarget($targetIdentifier) to specify the export target, may be a file or other
 * addTable($table) registres table to be exported , throws Exception when called twice for a single table drivers
 * addQuery($query) registers query, whose result should be exported, same as above
 * close()          method performs all the actions and closes the resources, throws exception if target is not writable
 * 
 */

/**
 *
 * The R3Import class provides methods to import files in different formats to postgres and oracle
 *
 * @category  Database import
 * @package   R3ImpExp
 * @author    Daniel Degasperi <daniel.degasperi@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
interface Driver {
    
    /**
     * Return the valid extension in a 2D array. The 1D is the generic name and the 2D is the index of the mandatory extensions
     * If no extension available null is returned (eg: database connection)
     *
     * @return array  The valid extension(s)
     * @access public
     */
    public function getExtensions();

    public function setLogger(LoggerInterface $logger);
    
    public function getLogger();
    
    // DD: get imported table name?
    // public function getOutputFiles();
    
    /**
     * Import the specified file to the specified database
     *
     * @param string   Source file name (or database connection)
     * @param string   Destination (schema.)table name
     * @param PDO      A valid PDO connection
     * @param array    Options. Valid options are:
     *  - srid: postgis srid (default -1)
     *  - create: If true the create the table (default true)
     *  - data: If true append the data to the table (default true)
     *  - geometry_column: The name of the geometry column  (default the_geom)
     *  - dump_format: 'B'=bulk, 'I'=insert statement (default 'B')
     *  - case_sensitive: true to maintain the case on the table. Default false
     *  - unique_field: Unique field name. If '' a gid field and a sequence are created.
     *  - force_int4: If true all the integer field are converted in int4. Default (false)
     *  - keep_precision: If true the precision of the data is maintained and NOT converted. Default true
     *  - simple_geometry: If true a simple geometry is created instead of a multi geomerty
     *  - source_encoding: Specify the character encoding of Shape's attribute column. (default : "ASCII")
     *  - policy: Specify NULL geometries handling policy (INSERT, SKIP, ABORT)
     *  - cmd_path: The path of the command(s) to execute. If empty the command must be in the system path. Default ''
     *  - tmp_path: The temporary path to use. Default '/tmp';
     *  - debug_level: specify the debug level (?)
     *  - table if multi-table format or database, set the table to import
     *  - table_nr if multi-table format or database, set the table index (start 0) to import
     *  - sql   if database, set the sql to execute to extract data (> priority than table)
     *  - read_buffer   the read buffer size (default 8192)
     *  - first_line_header  if true the first line of the file is the header line
     *  - separator  the field sepatatr character
     *  - line_feed  the CR (or LF or CRLF sequence)
     *  - quote_char the quote char
     * @return string  ?????????????
     * @access public
     */
    // abstract public function import($file, $table, PDO $db, array $opt = array());

}

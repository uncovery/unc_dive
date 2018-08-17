<?php
global $UNC_DIVE;
require_once('unc_dive_formats.inc.php');

/**
 * enumerate all dives in a file/database
 * returns associative array
 * 
 * @global type $UNC_DIVE
 * @param type $computer_type
 * @param type $file
 * @return type
 */
function unc_dive_list($computer_type, $file) {
    global $UNC_DIVE;
    $DB = unc_dive_connect_db($computer_type, $file);
    
    $F = $UNC_DIVE[$computer_type];

    $date_field = $F['fieldmap']['start_time']['field_name'];
    $date_format = $F['fieldmap']['start_time']['format'];
    $dive_number = $F['fieldmap']['dive_number']['field_name'];
    $table_name = $F['dive_table_name'];


    $filter = '';
    if (isset($F['filter'])) {
        $sql_filter = $F['filter'];
        $filter = 'WHERE ' . $sql_filter;
    }

    // insert the SELECT into the query
    $query = "SELECT $date_field as date_str, $dive_number as dive_number FROM $table_name $filter ORDER BY $date_field DESC;";
    $results = $DB->query($query);

    $dive_data = array();
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $date = unc_dive_data_convert($date_format, $row['date_str']);
        $dive_id = $row['dive_number'];
        $date_obj = new DateTime($date);
        $day = $date_obj->format("Y-m-d");
        $time = $date_obj->format("H:i:s");
        $dive_data[$day][$dive_id] = $time;
    }
    return $dive_data;
}

/**
 * Get all data for one dive
 * This, in the future, needs to be diversified so we can say what data we actually want to have.
 * 
 * @global type $UNC_DIVE
 * @param type $computer_type
 * @param type $source
 * @param type $dive_id
 * @return type
 */
function unc_dive_get_one($computer_type, $source, $dive_id) {
    global $UNC_DIVE;

    // first, get the data formats and fieldnames from the DB for the given $format
    $F = $UNC_DIVE[$computer_type]['fieldmap'];
    $table_name = $UNC_DIVE[$computer_type]['dive_table_name'];

    // let's make a SQL SELECT statement that uses the desired fieldnames
    $sql_elements = array();
    foreach ($F as $data_field => $data_info) {
        $sql_elements[] = $data_info['field_name'] . ' as ' . $data_field;
    }
    $sql_select = implode(", ", $sql_elements);

    // insert the SELECT into the query
    $query = "SELECT $sql_select FROM $table_name WHERE dive_number = $dive_id;";

    $DB = unc_dive_connect_db($computer_type, $source);

    // get my results
    $results = $DB->query($query);
    $row = $results->fetchArray(SQLITE3_ASSOC);

    $data_set = array();
    // get my data and convert it so that it's readable
    foreach ($F as $field_name => $field_data) {
        // if we need to convert something
        if (isset($field_data['format'])) {
            $data_set[$field_name] = unc_dive_data_convert($computer_type, $field_data['format'], $row[$field_name]);
        } else {
            $data_set[$field_name] = $row[$field_name];
        }
    }
    return $data_set;
}


/**
 * get the ID of the latest dive
 * 
 * @global type $UNC_DIVE
 * @param type $computer_type
 * @param type $source
 * @return boolean
 */
function unc_divelog_dive_latest($computer_type, $source) {
    global $UNC_DIVE;

    $DB = unc_dive_connect_db($computer_type, $source);
    
    $F = $UNC_DIVE[$computer_type]['fieldmap'];

    $table_name = $UNC_DIVE[$computer_type]['dive_table_name'];
    $dive_id_field = $F['dive_number']['field_name'];
    $date_field = $F['start_time']['field_name'];

    $filter = '';
    if (isset($UNC_DIVE[$computer_type]['filter'])) {
        $sql_filter = $UNC_DIVE[$computer_type]['filter'];
        $filter = 'WHERE ' . $sql_filter;
    }

    $query = "SELECT $dive_id_field AS dive_id FROM $table_name $filter ORDER BY $date_field DESC LIMIT 1";
    $results = $DB->query($query);
    if (count($results) == 0) {
        return false;
    }
    $row = $results->fetchArray(SQLITE3_ASSOC);
    return $row['dive_id'];
}

/**
 * make a database connection, based on the database format
 * 
 * @param type $computer_type
 * @param type $file
 * @return boolean
 */
function unc_dive_connect_db($computer_type, $file) {
    global $UNC_DIVE;
    $db_format = $UNC_DIVE[$computer_type]['db_format'];
    
    $function_name = 'unc_dive_connect_db_' . $db_format;
    if (function_exists($function_name)) {
        return $function_name($file);
    } else {
        return false;
    }
}

/**
 * Open a database connection to the datafile, SQLite3 format
 * 
 * @param type $file
 * @return \SQLite3|boolean
 */
function unc_dive_connect_db_sqlite($file) {
    if (!file_exists($file)) {
        echo "Could not read file $file, does not exist!";
        return false;
    }
    $database = new SQLite3($file, SQLITE3_OPEN_READONLY);
    if (!$database) {
        echo "Error with $file, could not understand SQLite3 format!";
        return false;
    }
    return $database;
}

function unc_dive_data_convert($computer_type, $data_type, $data) {
    global $UNC_DIVE;
    if (in_array($data_type, $UNC_DIVE['generic_formats'])) {
        return unc_dive_conversions_generic($data_type, $data);
    } else {
        $function_name = 'unc_dive_conversions_' . $computer_type;
        if (function_exists($function_name)) {
            return $function_name($data_type, $data);
        }
    }
    // nothing found
    return false;
}

/**
 * Generic, non-vendor specific data formats
 * 
 * @param type $format
 * @param type $data
 * @return type
 */
function unc_dive_conversions_generic($format, $data) {
    switch ($format) {
        case 'binary_float':
            $bin = hex2bin($data);
            $float = unpack("f", $bin);
            return round($float[1], 1);
        case 'hex': // simple hex such as 1F = 31
            $dec = hexdec($data);
            return $dec;
    }
    // format not found, return false;
    return false;
}

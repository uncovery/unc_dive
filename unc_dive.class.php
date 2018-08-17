<?php
error_reporting(E_ALL);

global $UNC_DIVE;
require_once('unc_dive_formats.inc.php');

/**
 * main class
 *
 */
class unc_dive {
    private $computer_type; // the data format of the requested computer (e.g. 'Suunto_D4i' )
    private $data_path; // path to the data file
    private $debug = false; // do we debug?
    
    public function __construct() {
        return true;
    }
    
    /**
     * set the source path
     * 
     * @param type $data_path
     */
    public function set_source($data_path) {
        if (file_exists($data_path)) {
            $this->data_path = $data_path;
            if ($this->debug) {
                echo "Data file found!\n";
            }
            return true;
        } else {
            echo "ERROR: File $data_path could not be found!\n";
            return false;
        }
    }
    
    /**
     * set the computer type
     * 
     * @param type $computer_type
     */
    public function set_computer_type($computer_type) {
        $this->computer_type = $computer_type;
        return true;
    }    
    
    public function set_debug($debug) {
        $this->debug = $debug;
        if ($debug) {
            echo "Debug enabled!";
        } 
        return true;
    }
         
    /**
     * retrieve the latest dive ID
     * 
     * @global type $UNC_DIVE
     * @global unc_dive $this
     * @return boolean
     */
    public function get_latest_dive_id() {
        global $UNC_DIVE;

        $DB = $this->connect_db();
        
        $F = $UNC_DIVE[$this->computer_type]['fieldmap'];

        $table_name = $UNC_DIVE[$this->computer_type]['dive_table_name'];
        $dive_id_field = $F['dive_number']['field_name'];
        $date_field = $F['start_time']['field_name'];

        $filter = '';
        if (isset($UNC_DIVE[$this->computer_type]['filter'])) {
            $sql_filter = $UNC_DIVE[$this->computer_type]['filter'];
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
     * enumerate all dives in a file/database
     * returns associative array
     * 
     * @global type $UNC_DIVE
     * @return type
     */
    public function dive_list() {
        global $UNC_DIVE;
        $DB = $this->connect_db();
        
        $F = $UNC_DIVE[$this->computer_type];

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
            $date = $this->data_convert($date_format, $row['date_str']);
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
    public function dive_get_one($dive_id) {
        global $UNC_DIVE;

        // first, get the data formats and fieldnames from the DB for the given $format
        $F = $UNC_DIVE[$this->computer_type]['fieldmap'];
        $table_name = $UNC_DIVE[$this->computer_type]['dive_table_name'];

        // let's make a SQL SELECT statement that uses the desired fieldnames
        $sql_elements = array();
        foreach ($F as $data_field => $data_info) {
            $sql_elements[] = $data_info['field_name'] . ' as ' . $data_field;
        }
        $sql_select = implode(", ", $sql_elements);

        // insert the SELECT into the query
        $query = "SELECT $sql_select FROM $table_name WHERE dive_number = $dive_id;";

        $DB = $this->connect_db();

        // get my results
        $results = $DB->query($query);
        $row = $results->fetchArray(SQLITE3_ASSOC);

        $data_set = array();
        // get my data and convert it so that it's readable
        foreach ($F as $field_name => $field_data) {
            // if we need to convert something
            if (isset($field_data['format'])) {
                $data_set[$field_name] = $this->data_convert($field_data['format'], $row[$field_name]);
            } else {
                $data_set[$field_name] = $row[$field_name];
            }
        }
        return $data_set;
    }    
    
    /**
     * connect to the database
     * 
     * @global type $UNC_DIVE
     * @return boolean
     */
    private function connect_db() {
        global $UNC_DIVE;
        $db_format = $UNC_DIVE[$this->computer_type]['db_format'];

        $method_name = 'connect_db_' . $db_format;
        if (method_exists($this, $method_name)) {
            return $this->$method_name();
        } else {
            echo "DB Connect failed, format not recognized! (connect_db)\n";           
            die();
        }        
    }
    
    
    /**
     * Open a database connection to the datafile, SQLite3 format
     * 
     * @param type $file
     * @return \SQLite3|boolean
     */
    private function connect_db_sqlite() {
        if (!file_exists($this->data_path)) {
            echo "DB file does not exist (connect_db_sqlite)!\n";             
            die();
        }
        $database = new SQLite3($this->data_path, SQLITE3_OPEN_READONLY);
        if (!$database) {
            echo "DB Load failed (connect_db_sqlite)!\n";             
            die();
        }
        return $database;
    }    

    protected function data_convert($data_type, $data) {
        global $UNC_DIVE;
        if (in_array($data_type, $UNC_DIVE['generic_formats'])) {
            return $this->conversions_generic($data_type, $data);
        } else {
            $method_name = 'convert_' . $this->computer_type;
            $c = new conversions();
            if (method_exists($c, $method_name)) {
                return $c->$method_name($data_type, $data);
            } else {
                die("ERROR (data_convert), invalid conversion method $method_name\n");
            }
        } 
        return false;
    }
    
    /**
     * Generic, non-vendor specific data formats
     * 
     * @param type $format
     * @param type $data
     * @return type
     */
    private function conversions_generic($format, $data) {
        switch ($format) {
            case 'binary_float':
                $bin = hex2bin($data);
                $float = unpack("f", $bin);
                return round($float[1], 1);
            case 'hex': // simple hex such as 1F = 31
                $dec = hexdec($data);
                return $dec;
        }
        if ($this->debug) {
            echo "conversions_generic failed! (data format not recognized!)\n";
        }
        return false;
    }    
    
}

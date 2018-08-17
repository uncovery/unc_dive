<?php

$UNC_DIVE = array(
    // we list data formats that are not model-specific here. those are processed in a common function
    'generic_formats' => array(
        'binary_float', 'hex',
    ),
    // SUUNTO D4i read with D5 dive software, exported SQLite DB, export this DB by using the "Backup" in the windows software
    'Suunto_D4i' => array(
        'db_format' => 'sqlite',
        'fieldmap' => array(
            'dive_number' => array('field_name' => 'DiveId'),
            'start_time' => array('field_name' => 'StartTime', 'format' => 'Suunto_D4i_seconds_since_0001'),
            'max_depth' => array('field_name' => 'MaxDepth'),
            'avg_depth' => array('field_name' => 'AvgDepth'),
            'serial_no' => array('field_name' => 'SerialNumber'),
            'dive_path' => array('field_name' => 'quote(SampleBlob)', 'format' => 'Suunto_D4i_SampleBlob'),
            'dive_time' => array('field_name' => 'Duration'), // in seconds
            'dive_type' => array('field_name' => 'Mode', 'format' => 'Suunto_D4i_dive_types'),
        ),
        'dive_table_name' => 'Dive',
        'filter' => 'Mode < 3', // no free diving for now
        'formats' => array(
                '3' => array(
                    // 03 pattern is in the format 1400 A470CD40 FFFFFF7F 1E       FFFF7F7 FFFFF7F7 FFFFF7F7F
                    'chunk_length' => 46,
                    'fields' => array('temp' => 'hex', 'depth' => 'binary_float', 'time' => 'hex'),
                    'pattern' => "/(?'time'[0-9A-F]{2})[0-9A-F]{2}(?'depth'[0-9A-F]{8})[0-9A-F]{8}(?'temp'[0-9A-F]{2}).*/",
                ),
                '4' => array(
                    // 04 pattern is in the format 1400 AE474140 FFFFFF7F FDFFEF41 FFFF7F7 FFFFF7F7 FFFFF7F7F
                    'chunk_length' => 52,
                    'fields' => array('temp' => 'binary_float', 'depth' => 'binary_float', 'time' => 'hex'),
                    'pattern' => "/(?'time'[0-9A-F]{2})[0-9A-F]{2}(?'depth'[0-9A-F]{8})[0-9A-F]{8}(?'temp'[0-9A-F]{8}).*/",
                ),
                '5' => array(
                    // 05 pattern is in the format 1400 3333AB40 FFFFFF7F FDFFE741 FFFF7F7F FFFF7F7F FFFF7F7F FFFF7F7F
                    'chunk_length' => 60,
                    'fields' => array('temp' => 'binary_float', 'depth' => 'binary_float', 'time' => 'hex'),
                    'pattern' => "/(?'time'[0-9A-F]{2})[0-9A-F]{2}(?'depth'[0-9A-F]{8})[0-9A-F]{8}(?'temp'[0-9A-F]{8}).*/",
                ),                
            ),   
        
        // below is only for reference
        'sample_data' => array(
            'DiveId' => 83,
            'StartTime' => '635932062070000000',
            'Duration' => 3105,
            'Mode' => 1, // dive mode, 1=Nitrox, 3 = Free dive
            'SourceSerialNumber' => NULL,
            'Source' => 'D4i',
            'MaxDepth' => 27.329999999999998,
            'SampleInterval' => 20, // the interval for the dive blob
            'Note' => '',
            'StartTemperature' => 31,
            'BottomTemperature' => 29,
            'EndTemperature' => 29,
            'StartPressure' => NULL,
            'EndPressure' => NULL,
            'AltitudeMode' => 0,
            'PersonalMode' => 0,
            'CylinderVolume' => 12,
            'CylinderWorkPressure' => 232000,
            'ProfileBlob' => 'Blob',
            'TemperatureBlob' => 'Blob',
            'PressureBlob' => 'Blob',
            'DiveNumberInSerie' => 2,
            'TissuePressuresNitrogenStartBlob' => 'Blob',
            'TissuePressuresHeliumStartBlob' => 'Blob',
            'SurfaceTime' => 5280,
            'CnsStart' => 8,
            'OtuStart' => 32,
            'OlfEnd' => 26,
            'DeltaPressure' => NULL,
            'DivingDaysInRow' => NULL,
            'SurfacePressure' => 101800,
            'PreviousMaxDepth' => NULL,
            'DiveTime' => NULL,
            'Deleted' => NULL,
            'Weight' => 0,
            'Weather' => 0,
            'Visibility' => 0,
            'DivePlanId' => NULL,
            'SetPoint' => NULL,
            'AscentTime' => NULL,
            'BottomTime' => NULL,
            'CnsEnd' => 23,
            'OtuEnd' => 77,
            'TissuePressuresNitrogenEndBlob' => 'Blob',
            'TissuePressuresHeliumEndBlob' => 'Blob',
            'Boat' => NULL,
            'SampleBlob' => 'Blob',
            'AvgDepth' => 17.379999999999999,
            'Algorithm' => 1,
            'LowSetPoint' => NULL,
            'LowSwitchPoint' => NULL,
            'HighSwitchPoint' => NULL,
            'MinGf' => NULL,
            'MaxGf' => NULL,
            'Partner' => NULL,
            'Master' => NULL,
            'DesaturationTime' => NULL,
            'Software' => '1.2.10',
            'SerialNumber' => '33200647',
            'TimeFromReset' => NULL,
            'Battery' => 3.0999999046325684,
        ),
    )
);

function unc_dive_conversions_Suunto_D4i($format, $data) {
    global $UNC_DIVE;
    $type_var = 'Suunto_D4i';
    switch ($format) {
        case $type_var . '_dive_types': 
            switch ($data) {
                case 0: 
                    return 'Dive'; // does this exist?
                case 1:
                    return 'Nitrox Dive';
                case 2:
                    return 'Dive';
                case 3:
                    return 'Free Dive';
            }
        case $type_var . '_SampleBlob': // four-digit hex format of a float such as D7 A3 D0 3F = 1.63...
            //  see here: http://lists.subsurface-divelog.org/pipermail/subsurface/2014-November/015798.html
            // strip of X'
            $data_clean = substr($data, 2);
            $data_type = substr($data_clean, 1, 1);
            $chunk_split = $UNC_DIVE[$type_var]['formats'][$data_type];
            $pattern = $chunk_split['pattern'];
            $fields = $chunk_split['fields'];
            $data_clipped = substr($data_clean, 2);
            $data_str = chunk_split($data_clipped, $chunk_split['chunk_length'], "|");
            $dive_array = explode("|", $data_str);            
            $dive_path = array();
            $i = 0;
            foreach ($dive_array as $dive_str) {
                $results = false;
                preg_match($pattern, $dive_str, $results);
                foreach ($fields as $field => $format) {
                    if (!isset($results[$field])) {
                        continue;
                    }
                    $data = $results[$field];
                    $converted = unc_dive_data_convert($type_var, $format, $data);
                    $dive_path[$i][$field] = $converted; // D4i measures every 20 seconds
                }
                $i++;
            }
            return $dive_path;
        case $type_var . '_seconds_since_0001': // suunto UNIX-like timestamp
            $number_of_seconds = 62135600400;
            $seconds = $data / 10000000 - $number_of_seconds;
            $date = new DateTime();
            $date->setTimestamp($seconds);
            $date_str = $date->format("Y-m-d H:i:s");
            return $date_str;
    }
}

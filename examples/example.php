<?php

require_once('./../unc_dive.inc.php');

$source_format = 'D4i';

// please provide your own backup file from the D4i computer
$source_file = './sample.db';

// list all dives 
$avail_dives = unc_dive_list($source_format, $source_file);

// get the id of the latest dive
$latest_dive_id = unc_divelog_dive_latest($source_format, $source_file);

// get the dive data for the latest dive
$data = unc_dive_get_one($source_format, $source_file, $latest_dive_id);
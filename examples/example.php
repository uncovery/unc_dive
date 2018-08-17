<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

// please provide your own backup file from the D4i computer
$data = "./default.db";
$type = "Suunto_D4i";

// requiring class...
require_once('./../unc_dive.inc.php');

// creating instance....
$dive = new unc_dive();
var_dump($dive);

$dive->set_debug(false);

$dive->set_computer_type($type);
$dive->set_source($data);

$latest = $dive->get_latest_dive_id();

echo "latest dive ID: $latest\n";
// var_dump($latest);

$list_dives = $dive->dive_list();

$no_dives = count($list_dives);

echo "$no_dives dives found!\n";
// var_dump($list_dives);

$dive_data = $dive->dive_get_one($latest);

// var_dump($dive_data);

$no_dive_points = count($dive_data);

echo "$no_dive_points dive data found\n";

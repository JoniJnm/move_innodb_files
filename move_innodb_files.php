<?php

/**
 * MOVE INNODB TABLES COPYING idb FILES FROM ONE MySQL DATA FOLDER TO ANOTHER
 *
 * · The tables must be created with innodb_file_per_table
 * · You need the structure of all tables (with auto_increment value)
 * · Tested with MySQL 5.5 and 5.6
 *
 * @version		1
 * @copyright	Copyright (C) 2013 JoniJnm.es
 * @license		GNU/GPL
 */

DEFINE("DB_DATA_FOLDER", 'C:/Users/Blink/Desktop/data2/db2'); //folder of ibd files to import
DEFINE('MYSQL_DATA_DIR', 'D:/xampp/mysql/data'); //mysql datadir folder (see my.ini config file)

DEFINE('DB_HOST', 'localhost');
DEFINE('DB_NAME', 'dbprod'); //must have the estructure of all tables (with auto_increment value) before run this script
DEFINE('DB_USER', 'root');
DEFINE('DB_PASS', '');

DEFINE("DB_TMP", "tmp1"); //temporal database

set_time_limit(0);

$currentID = -1;

function debug($msg = '') {
	echo $msg." <br />\n";
	flush();
}

function error($msg) {
	debug("ERRROR: ");
	debug($msg);
	exit;
}

function query($query) {
	global $db;
	if (!$db->query($query)) {
		error($db->error." <br />\n".$query);
	}
}

function connect($dbname='') {
	global $db;
	$db = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbname);
	if ($db->connect_error) {
		error($mysqli->connect_error." (".$mysqli->connect_errno.")");
	}
}

function getTB($file) {
	//copy: http://www.chriscalender.com/?p=28
	$offset = 2;
	$handle = fopen($file, "rb");
	if (!$handle) die("cannot open file");
	$ret = -1;

	for ($z = 0; $z <= 18; $z++) {
		$contents = fread($handle, $offset);
		if ($z == 18) {
			$contents2 = bin2hex($contents);
			$contents3 = hexdec($contents2);
			$ret = $contents3;
		}
	}
	fclose($handle);
	if ($ret == -1) {
		error("Error getting tablespace id from ".$file);
	}
	return $ret;
}

function increaseTB($hasta) {
	global $currentID;
	if ($hasta == -1) error("\$hasta no puede ser -1");
	if (!$currentID == -1) error("\$currentID NO SE HA INICIADO");
	if ($currentID > $hasta) error("\$currentID > \$hasta: $currentID > $hasta. Use alternative script or use a new clean mysql data folder (with xampp, for ex.)");
	for (; $currentID < $hasta; $currentID++) {
		query("CREATE TABLE tmp_".$currentID." (id int) ENGINE=InnoDB");
	}
}

function fixTable($tbname) {
	global $currentID;
	increaseTB(getTB(DB_DATA_FOLDER.'/'.$tbname.'.ibd')-1);
	query("CREATE TABLE `".$tbname."` LIKE ".DB_NAME.".`".$tbname."`");
	$currentID++;
	query("ALTER TABLE `".$tbname."` DISCARD TABLESPACE");
	copy(DB_DATA_FOLDER.'/'.$tbname.'.ibd', MYSQL_DATA_DIR.'/'.DB_TMP.'/'.$tbname.'.ibd');
	query("ALTER TABLE `".$tbname."` IMPORT TABLESPACE");
}

if (!file_exists(MYSQL_DATA_DIR)) {
	error("The folder ".MYSQL_DATA_DIR." doesn't exists. See my.ini mysql config file, datadir value.");
}

$ibd_files = glob(DB_DATA_FOLDER.'/*.ibd');
if (!count($ibd_files)) {
	error("Seems that the tables was created without innodb_file_per_table :( (No idb files found)");
}

$step = isset($_GET['step']) ? $_GET['step'] : 1;

connect();
query("CREATE DATABASE ".DB_TMP);
query("USE ".DB_TMP);
$randtb = "tmp_".time();
query("CREATE TABLE `".$randtb."` (id int) ENGINE=InnoDB");
$currentID = getTB(MYSQL_DATA_DIR.'/'.DB_TMP.'/'.$randtb.'.ibd');

$tablas = array();
foreach (glob(DB_DATA_FOLDER.'/*.ibd') as $file) {
	$name = substr(basename($file), 0, -4);
	$tablas[$name] = getTB($file);
}

asort($tablas);
foreach ($tablas as $tabla=>$id) {
	debug("Fix table $tabla");
	fixTable($tabla);
}

foreach ($tablas as $tabla=>$id) {
	query("DROP TABLE ".DB_NAME.".`".$tabla."`");
	query("ALTER TABLE `".$tabla."` RENAME ".DB_NAME.".`".$tabla."`");
}

query("DROP DATABASE ".DB_TMP);

debug();
debug();
debug("DONE!");
debug("Restart MySQL");

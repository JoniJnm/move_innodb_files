<?php

/**
 * MOVE INNODB TABLES COPYING idb FILES FROM ONE MySQL DATA FOLDER TO ANOTHER
 *
 * · The tables must be created with innodb_file_per_table
 * · You need the structure of all tables (with auto_increment value)
 * · Works only with MySQL 5.6+
 *
 * @version		1
 * @copyright	Copyright (C) 2013 JoniJnm.es
 * @license		GNU/GPL
 */

DEFINE("DB_DATA_FOLDER", 'C:/Users/Blink/Desktop/data2/db2'); //folder of ibd files to import
DEFINE('MYSQL_DATA_DIR', 'D:/xampp/mysql/data'); //mysql datadir folder (see my.ini config file)

DEFINE('DB_HOST', 'localhost');
DEFINE('DB_NAME', 'dbprod');
DEFINE('DB_USER', 'root');
DEFINE('DB_PASS', '');

set_time_limit(0);

function debug($msg = '') {
	echo $msg." <br />\n";
	ob_flush();
        flush();
}

function error($msg) {
	debug("ERRROR: ");
	debug($msg);
        ob_end_flush();
	exit;
}

function query($query) {
	global $db;
	if (!$db->query($query)) {
		error($db->error." <br />\n".$query);
	}
}

if (ob_get_level() == 0) ob_start();

if (!file_exists(MYSQL_DATA_DIR)) {
	error("The folder ".MYSQL_DATA_DIR." doesn't exists. See my.ini mysql config file, datadir value.");
}

$ibd_files = glob(DB_DATA_FOLDER.'/*.ibd');
if (!count($ibd_files)) {
	error("Seems that the tables was created without innodb_file_per_table :( (No idb files found)");
}

$step = isset($_GET['step']) ? $_GET['step'] : 1;

if ($step == 1) {
	echo "STEP 1, DO: <br />\n";
	if (file_exists(MYSQL_DATA_DIR."/".DB_NAME)) {
		debug("· DROP DATABASE ".DB_NAME);
	}
	debug("· CREATE DATABASE ".DB_NAME);
	debug("· IMPORT sql STRUCTURE (with auto_increment values)");
	debug("· STOP MYSQL");
	debug();
	debug("and click <a href='?step=2'>here</a>");
}
elseif ($step == 2) {
	$db = @new mysqli(DB_HOST, DB_USER, DB_PASS);
	if (!$db->connect_error) error("Seems that mysql is running <a href=''>retry</a>");
	foreach ($ibd_files as $file) {
		$name = substr(basename($file), 0, -4);
		if (!file_exists(MYSQL_DATA_DIR.'/'.DB_NAME.'/'.$name.'.ibd')) {
			error("The table ".$name." doesn't exists. Did you import the structure? <br />\n<a href='?step=1'>retry</a>");
		}
		if (!unlink(MYSQL_DATA_DIR.'/'.DB_NAME.'/'.$name.'.ibd')) {
			error("cannot delete ".MYSQL_DATA_DIR.'/'.DB_NAME.'/'.$name.'.ibd');
		}
		copy(DB_DATA_FOLDER.'/'.$name.'.ibd', MYSQL_DATA_DIR.'/'.DB_NAME.'/'.$name.'.ibd');
	}
	debug("Start mysql and click <a href='?step=3'>here</a>");
}
elseif ($step == 3) {
	$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	if ($db->connect_error) error($db->connect_error." (".$db->connect_errno.") <br />\n<a href=''>retry</a>");
	foreach (glob(DB_DATA_FOLDER.'/*.ibd') as $file) {
		$name = substr(basename($file), 0, -4);
		debug("Fix table ".$name);
		query("ALTER TABLE `".$name."` discard tablespace");
		query("ALTER TABLE `".$name."` import tablespace");
	}
	debug();
	debug("DONE!");
	debug("Restar MySQL");
}

ob_end_flush();
<?php
require_once './configuration.php';
require_once './functions.php';
session_write_close();
header('Content-Type: application/json');

$object = new stdClass();
$object->error = true;
$object->filename = "";
$object->size = 0;
$object->msg = "";

if (empty($_REQUEST['secret']) || $_REQUEST['secret'] !== $global['secret']) {    
    $object->msg = 'Incorrect Secret';
    error_log($object->msg);
    die(json_encode($object));
}

if (empty($_GET['filename'])) {
    $object->msg = 'FILESIZE GET file not found';
    error_log($object->msg);
    die(json_encode($object));
}

$object->filename = $_GET['filename'];

$object->size = getUsageFromFilename($_GET['filename']);
$object->modified = getModifiedTimeFromFilename($_GET['filename']);

$object->error = false;
die(json_encode($object));

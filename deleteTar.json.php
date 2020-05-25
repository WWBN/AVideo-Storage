<?php

require_once './configuration.php';
set_time_limit(3600);// 1 hour
header('Content-Type: application/json');
$obj = new stdClass();
$obj->error = true;
$obj->msg = "";
$obj->aVideoStorageURL = $global['aVideoStorageURL'];
$obj->filename = "";
$obj->filesize = 0;

if (empty($_REQUEST['secret']) || $_REQUEST['secret'] !== $global['secret']) {
    $obj->msg = "Invalid secret";
} else if (empty($_REQUEST['filename'])) {
    $obj->msg = "Empty filename";
} else {
    $filename = $_REQUEST['filename'];
    $obj->tarFileName = "{$filename}.tgz";
    $videosDir = "{$global['videos_directory']}";
    $backupFile = "{$videosDir}{$obj->tarFileName}";
    if (file_exists($backupFile)) {
        unlink($backupFile);
    } else {
        error_log("deleteTar ERROR ({$backupFile}) is does NOT exists ");
    }

    $obj->error = false;
}
die(json_encode($obj));
?>

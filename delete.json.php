<?php

function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object))
                    rrmdir($dir . "/" . $object);
                else
                    unlink($dir . "/" . $object);
            }
        }
        rmdir($dir);
    }
}

require_once './configuration.php';

header('Content-Type: application/json');
$obj = new stdClass();
$obj->error = true;
$obj->msg = "";
$obj->youPHPTubeStorageURL = $global['youPHPTubeStorageURL'];
$obj->filename = "";

if (empty($_REQUEST['secret']) || $_REQUEST['secret'] !== $global['secret']) {
    $obj->msg = "Invalid secret";
} else if (empty($_REQUEST['filename'])) {
    $obj->msg = "Empty filename";
} else {
    $filename = $_REQUEST['filename'];

    $files = glob("{$global['videos_directory']}*{$filename}*"); // get all file names
    foreach ($files as $file) { // iterate files
        if (is_file($file))
            unlink($file); // delete file
        else {
            rrmdir($file);
        }
    }
    
    $obj->error = false;
}

die(json_encode($obj));
?>

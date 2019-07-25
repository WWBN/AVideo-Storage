<?php

require_once './configuration.php';
require_once './functions.php';

header('Content-Type: application/json');
$obj = new stdClass();
$obj->error = true;
$obj->msg = "";
$obj->youPHPTubeStorageURL = $global['youPHPTubeStorageURL'];
$obj->filename = "";

if (empty($_REQUEST['secret']) || $_REQUEST['secret'] !== $global['secret']) {
    $obj->msg = "Invalid secret";
} else if (empty($_REQUEST['video_url'])) {
    $obj->msg = "Empty Video URL";
} else {
    $url = $_REQUEST['video_url'];
    $name = basename($url); // to get file name
    $ext = pathinfo($url, PATHINFO_EXTENSION); // to get extension
    $name2 = pathinfo($url, PATHINFO_FILENAME); //file name without extension
    $extParts = explode("?", $ext);
    $ext = $extParts[0];
    
    if (strtolower($ext) === 'mp4' || strtolower($ext) === 'webm') {
        $file = url_get_contents($_REQUEST['video_url']); // to get file
        if ($file) {
            $obj->filename = "{$global['videos_directory']}{$name2}.{$ext}";
            if (file_put_contents($obj->filename, $file)) {
                $obj->error = false;
                $obj->msg = "";
            } else {
                $obj->msg = "Error on save file {$obj->filename}";
            }
        } else {
            $obj->msg = "Error on download URL {$url}";
        }
    } else if (strtolower($ext) === 'tgz') {
        $obj->filename = "{$global['videos_directory']}{$name2}.{$ext}";
        
        $obj = moveFromSiteToLocalHLS($url, $obj->filename);
    } else {
        $obj->msg = "Extension Not Allowed {$ext}";
    }
}

die(json_encode($obj));
?>

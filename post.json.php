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

    if (strtolower($ext) === 'mp4' || strtolower($ext) === 'webm') {
        $file = url_get_contents($url); // to get file
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
        $cmd = "wget {$url} -O {$obj->filename}";

        error_log("Get HLS Start ({$cmd})");
        //echo $cmd;
        exec($cmd . " 2>&1", $output, $return_val);

        if ($return_val === 0) {
            if(filesize($obj->filename)<1000000){ // less then 1 mb
                $obj->msg = "The filesize is smaller then 1 Mb ";
            }else{
                $directory = "{$global['videos_directory']}{$name2}";
                if(!is_dir($directory)){
                    mkdir($directory);
                }
                $cmd = "tar --overwrite  -xvf {$obj->filename} -C {$directory}";
                error_log("restoreVideos HLS {$cmd}");
                //echo $cmd;exit;
                exec($cmd . " 2>&1", $output, $return_val);
                if ($return_val === 0) {
                    $obj->error = false;
                    unlink($obj->filename);
                } else {
                    $obj->msg = "Error on command {$cmd} ".implode("<br>", $output);
                }
            }
        } else {
            $obj->msg = "Error on command {$cmd} ".implode("<br>", $output);
        }
    } else {
        $obj->msg = "Extension Not Allowed {$ext}";
    }
}

die(json_encode($obj));
?>

<?php

require_once './configuration.php';
require_once './functions.php';

header('Content-Type: application/json');
$obj = new stdClass();
$obj->error = true;
$obj->msg = "";
$obj->aVideoStorageURL = $global['aVideoStorageURL'];
$obj->filename = "";

ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');
error_log("post.json.php: start ". json_encode($_REQUEST));
if (empty($_REQUEST['secret']) || $_REQUEST['secret'] !== $global['secret']) {
    $obj->msg = "Invalid secret";
} else if (empty($_REQUEST['video_url'])) {
    $obj->msg = "Empty Video URL";
} else {
    error_log("post.json.php: secret and video confirmed ");
    $url = $_REQUEST['video_url'];
    $name = basename($url); // to get file name
    $ext = pathinfo($url, PATHINFO_EXTENSION); // to get extension
    $name2 = pathinfo($url, PATHINFO_FILENAME); //file name without extension
    if (!empty($_REQUEST['source_secret'])) {
        $query = parse_url($url, PHP_URL_QUERY);
        // Returns a string if the URL has parameters or NULL if not
        if ($query) {
            $url .= "&secret={$_REQUEST['source_secret']}";
        } else {
            $url .= "?secret={$_REQUEST['source_secret']}";
        }
    }
    if(isLocked($url)){
        $obj->msg = "We still processing the URL {$url}";
        error_log("post.json.php: We still processing the URL {$url}");
    }else{
        error_log("post.json.php: not locked ");
        lock($url);
        $extParts = explode("?", $ext);
        $ext = $extParts[0];
        error_log("post.json.php: request extension {$ext} on URL {$_REQUEST['video_url']}");
        if (strtolower($ext) === 'mp4' || strtolower($ext) === 'webm') {
            $obj->filename = "{$global['videos_directory']}{$name2}.{$ext}";
            error_log("post.json.php: requesting wget $url, $obj->filename");
            $wgetResp = wget($url, $obj->filename);
            if ($wgetResp) {
                error_log("post.json.php: wget respond fine $url, $obj->filename");
                $obj->error = false;
                $obj->msg = "";
            } else {
                error_log("post.json.php: get URL {$url}");
                $file = url_get_contents($url); // to get file
                error_log("post.json.php: Download done");
                if ($file) {
                    $size = strlen($file);
                    error_log("post.json.php: is file {$size} = " . humanFileSize($size));
                    if ($size > 1000) {
                        $destinationSize = @filesize($obj->filename);
                        if ($destinationSize > 1000) {
                            if ($size == $destinationSize) {
                                $obj->error = false;
                                $obj->msg = "The file {$obj->filename} is there already but they look the same, we download ({$size}) = " . humanFileSize($size) . " and the we found on the storage {$destinationSize} = " . humanFileSize($destinationSize);
                            } else {
                                $obj->error = true;
                                $obj->msg = "Error the file {$obj->filename} is there already: we download ({$size}) = " . humanFileSize($size) . " and the we found on the storage {$destinationSize} = " . humanFileSize($destinationSize);
                            }
                            error_log("post.json.php: {$obj->msg}");
                        } else if (file_put_contents($obj->filename, $file)) {
                            $obj->error = false;
                            $obj->msg = "";
                        } else {
                            $obj->msg = "Error on save file {$obj->filename}";
                            error_log("post.json.php: {$obj->msg}");
                        }
                    } else {
                        error_log("post.json.php: file too small: {$file}");
                        $obj->msg = "Error on download URL {$url}";
                    }
                } else {
                    error_log("post.json.php: empty file");
                    $obj->msg = "Error on download URL {$url}";
                }
            }
        } else if (strtolower($ext) === 'tgz') {
            $obj->filename = "{$global['videos_directory']}{$name2}.{$ext}";

            error_log("post.json.php: Download HLS {$obj->filename}");
            $obj = moveFromSiteToLocalHLS($url, $obj->filename);
        } else {
            $obj->msg = "Extension Not Allowed {$ext}";
        }
        removeLock($url);
    }
}
$json = json_encode($obj);
error_log("post.json.php: response {$json}");
die($json);
?>

<?php

function getPathToApplication() {
    return str_replace("index.php", "", $_SERVER["SCRIPT_FILENAME"]);
}

function getURLToApplication() {
    $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url = explode("index.php", $url);
    $url = $url[0];
    return $url;
}

function url_get_contents($Url, $ctx = "") {
    global $global;
    $session = @$_SESSION;
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }

    if (empty($ctx)) {
        $opts = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
                "allow_self_signed" => true,
            ),
        );
        $context = stream_context_create($opts);
    } else {
        $context = $ctx;
    }
    if (ini_get('allow_url_fopen')) {
        try {
            $tmp = @file_get_contents($Url, false, $context);
            @session_start();
            $_SESSION = $session;
            if ($tmp != false) {
                return $tmp;
            }
        } catch (ErrorException $e) {
            
        }
    } else if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        @session_start();
        $_SESSION = $session;
        return $output;
    }
    $result = @file_get_contents($Url, false, $context);
    @session_start();
    $_SESSION = $session;
    return $result;
}

function moveFromSiteToLocalHLS($url, $filename, $newTry = 0) {
    global $global;
    $obj = new stdClass();
    $obj->error = true;
    $obj->msg = "";
    $obj->youPHPTubeStorageURL = $global['youPHPTubeStorageURL'];
    $obj->filename = $filename;
    
    $cmd = "wget {$url} -O {$filename}";

    error_log("Get HLS Start ({$cmd})");
    //echo $cmd;
    exec($cmd . " 2>&1", $output, $return_val);
    //$return_val = file_put_contents($filename, url_get_contents("{$url}"));
    if ($return_val === 0) {
        if (filesize($filename) < 1000000) { // less then 1 mb
            $obj->msg = "The filesize is smaller then 1 Mb ";
        } else {
            $name2 = pathinfo($url, PATHINFO_FILENAME); //file name without extension
            $directory = "{$global['videos_directory']}{$name2}";
            if (!is_dir($directory)) {
                mkdir($directory);
            }
            $cmd = "tar --overwrite  -xvf {$filename} -C {$directory}";
            error_log("restoreVideos HLS {$cmd}");
            //echo $cmd;exit;
            exec($cmd . " 2>&1", $output, $return_val);
            if ($return_val === 0) {
                $obj->error = false;
                unlink($filename);
            } else {
                if(empty($newTry) && $newTry < 3){
                    // try again to check if you can get the tar done.
                    error_log("0 - moveFromSiteToLocalHLS: fail to unpack, Trying again ($newTry)");
                    $newTry += 1;
                    sleep($newTry*10);
                    moveFromSiteToLocalHLS($url, $filename, $newTry);
                }
                $obj->msg = "moveFromSiteToLocalHLS: Error on command {$cmd} ";
                error_log("moveFromSiteToLocalHLS: Error on command {$cmd} " . json_encode($output));
            }
        }
    } else {
        $obj->msg = "moveFromSiteToLocalHLS: Error on command {$cmd} ";
        error_log($obj->msg);
    }

    return $obj;
}

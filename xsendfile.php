<?php

require_once './configuration.php';
require_once './functions.php';
session_write_close();

if (!empty($_GET['test'])) {    
    $path = getcwd().'/xtest.txt';    
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=successtest.txt');
    header('Content-Transfer-Encoding: binary');
    header('Connection: Keep-Alive');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header("X-Sendfile: {$path}");
    header('Content-Length: ' . filesize($path));
    exit;
}

if (empty($_GET['file'])) {
    error_log("XSENDFILE GET file not found ");
    die('GET file not found');
}

if (empty($_GET['token'])) {
    $_GET['token'] = 0;
}

$_GET['file'] = str_replace(".m3u8.mp4", '.m3u8', $_GET['file']);

$path_parts = pathinfo($_GET['file']);
$file = $path_parts['basename'];
$path = "{$global['videos_directory']}{$file}";

if ($path_parts["extension"] === "m3u8" || $path_parts["extension"] === "key") {
    $arr = explode("/", $path_parts["dirname"]);
    $sfilename = end($arr);
    $path = "{$global['videos_directory']}{$sfilename}/{$file}";
    if (!file_exists($path)) {
        // probably resolution m3u8
        $skipAuthorization = 1;
        $path = "{$global['videos_directory']}{$path_parts["dirname"]}/{$file}";
    }
} else {
    $sfilename = $path_parts['filename'];
}
if ($path_parts["extension"] == 'ts') {
    $skipAuthorization = 1;
}

if (!empty($skipAuthorization)) {
    
} else
if (!empty($_REQUEST['secret']) && $_REQUEST['secret'] === $global['secret']) {
    error_log("Storage xsendfile with secret");
} else {
    $url = "{$global['aVideoURL']}plugin/YPTStorage/canWatchVideo.json.php?token={$_GET['token']}&filename={$sfilename}";
    $json = url_get_contents($url);

    error_log("Storage xsendfile {$url} => {$json}");

    if (empty($json)) {
        die("Streamer error: {$url}");
    }

    $jsonObject = json_decode($json);
    if (empty($jsonObject->authorization)) {
        die("Not authorized: " . $json);
    }
}

if (file_exists($path)) {
    if (!empty($_GET['download'])) {

        if ($path_parts["extension"] === "m3u8") {
            downloadHLS($path);
        }

        if (!empty($_GET['title'])) {
            $quoted = sprintf('"%s"', addcslashes(basename($_GET['title']), '"\\'));
        } else {
            $quoted = sprintf('"%s"', addcslashes(basename($_GET['file']), '"\\'));
        }

        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . $quoted);
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
    } else if (!empty($_GET['playHLSasMP4'])) {
        playHLSasMP4($path);
    }
    header("X-Sendfile: {$path}");
    if (empty($_GET['download'])) {
        header("Content-type: " . mime_content_type($path));
    }
    header('Content-Length: ' . filesize($path));
    die();
} else {
    error_log("XSENDFILE ERROR: Not exists {$path} = " . json_encode($path_parts));
}

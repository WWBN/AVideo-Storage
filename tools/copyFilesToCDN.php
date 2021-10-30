<?php

function ftp_mkdir_recusive($path) {
    global $conn_id;
    $parts = explode("/", $path);
    array_pop($parts);
    $return = true;
    $fullpath = "";
    foreach ($parts as $part) {
        if (empty($part)) {
            $fullpath .= "/";
            continue;
        }
        $fullpath .= $part . "/";
        if (@ftp_chdir($conn_id, $fullpath)) {
            ftp_chdir($conn_id, $fullpath);
        } else {
            if (@ftp_mkdir($conn_id, $part)) {
                ftp_chdir($conn_id, $part);
            } else {
                $return = false;
            }
        }
    }
    return $return;
}

require_once '../configuration.php';
//streamer config
require_once '../functions.php';


if (!isCommandLineInterface()) {
    return die('Command Line only');
}

//$storage_hostname = 'storage.ypt.me';
//$storage_username = '';
//$storage_password = '';
// set up basic connection
$conn_id = ftp_connect($storage_hostname);

// login with username and password
$login_result = ftp_login($conn_id, $storage_username, $storage_password);
ftp_pasv($conn_id, true);

$glob = glob("../videos/*");
$totalItems = count($glob);
echo "Found total of {$totalItems} items " . PHP_EOL;
$countItems = 0;
foreach ($glob as $file) {
    $countItems++;
    echo "[$countItems/$totalItems] Process file {$file} " . PHP_EOL;
    $dirName = getCleanFilenameFromFile($file);
    $filesToUpload = array();
    if (is_dir($file)) {
        //$parts = explode('/videos/', $file);
        //$dirName = rtrim($parts[1], '/');
        $glob2 = glob("{$file}/*");
        foreach ($glob2 as $file2) {
            if (is_dir($file2)) {
                $glob3 = glob("{$file2}/*");
                foreach ($glob3 as $file3) {
                    $filesToUpload[] = $file3;
                }
            } else {
                $filesToUpload[] = $file2;
            }
        }
    } else {
        $filesToUpload[] = $file;
    }

    foreach ($filesToUpload as $value) {
        $parts = explode('/videos/', $value);
        $remote_file = "{$dirName}/{$parts[1]}";

        $res = ftp_size($conn_id, $remote_file);
        if ($res > 0) {
            echo "File $remote_file already exists" . PHP_EOL;
        } else {
            $filesize = filesize($value);
            $start = microtime(true);
            $filesizeMb = $filesize/(1024*1024);
            echo "Uploading $value to $remote_file ".number_format($filesizeMb,2)."MB" . PHP_EOL;
            //ftp_mkdir_recusive($remote_file);
            if (ftp_put($conn_id, $remote_file, $value, FTP_ASCII)) {
                echo "successfully uploaded $value\n";
                $end = number_format(microtime(true)-$start);
                if(!empty($end)){
                    $ETA = secondsToVideoTime($end*($totalItems-$countItems));
                    echo number_format($filesizeMb,2)."MB Uploaded in ".secondsToVideoTime($end).' '. number_format($filesizeMb/$end,1)."Mbps ETA:{$ETA}".PHP_EOL;
                }
            } else {
                echo "There was a problem while uploading $file\n";
            }
        }
    }
}

// close the connection
ftp_close($conn_id);

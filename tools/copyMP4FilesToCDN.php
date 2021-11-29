<?php

$totalSameTime = 3;

function getConnID() {
    global $conn_id, $storage_hostname, $storage_username, $storage_password;
    if (empty($conn_id)) {
        $conn_id = ftp_connect($storage_hostname);
        // login with username and password
        $login_result = ftp_login($conn_id, $storage_username, $storage_password);
        ftp_pasv($conn_id, true);
    }
    return $conn_id;
}

function getRemoteFileName($value) {
    global $dirName;
    $path_parts = pathinfo($value);
    if (empty($path_parts['extension'])) {
        echo "Skip empty extension {$value}" . PHP_EOL;
        return false;
    }
    if ($path_parts['extension'] == 'tgz') {
        echo "Skip tgz {$value}" . PHP_EOL;
        return false;
    }

    $parts = explode('/videos/', $value);
    $remote_file = "{$dirName}/{$parts[1]}";
    $remote_file = str_replace("{$dirName}/{$dirName}/", "$dirName/", $remote_file);
    return $remote_file;
}

function upload($value) {
    global $totalBytes, $totalUploadedSize, $ret, $countItems, $totalItems, $filesToUploadCount, $totalFilesToUpload, $ignoreRemoteCheck;
    $remote_file = getRemoteFileName($value);
    if (empty($remote_file)) {
        return false;
    }
    $filesize = filesize($value);
    if (empty($filesize) || $filesize < 20) {
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] $value empty filesize" . PHP_EOL;
        return false;
    }
    $connID = getConnID();
    if (file_exists($value)) {
        $totalBytes += $filesize;
        $totalUploadedSize += $filesize;
        $filesizeMb = $filesize / (1024 * 1024);
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] Uploading $value to $remote_file " . number_format($filesizeMb, 2) . "MB" . PHP_EOL;
        //ftp_mkdir_recusive($remote_file);

        return ftp_put($connID, $remote_file, $value, FTP_BINARY);
    }
}

require_once '../configuration.php';
//streamer config
require_once '../functions.php';


if (!isCommandLineInterface()) {
    return die('Command Line only');
}

$index = intval(@$argv[1]);

$ignoreRemoteCheck = 1;

$totalSameTimeArg = intval(@$argv[2]);
if (!empty($totalSameTimeArg)) {
    $totalSameTime = $totalSameTimeArg;
}

//$storage_hostname = 'storage.ypt.me';
//$storage_username = '';
//$storage_password = '';
// set up basic connection

echo "Connect to $storage_hostname MAX {$totalSameTime}" . PHP_EOL;

$glob = glob("../videos/*");
$totalItems = count($glob);

echo "Found total of {$totalItems} items " . PHP_EOL;
for ($countItems = 0; $countItems < $totalItems;) {
    $skip = true;
    $file = $glob[$countItems];
    $countItems++;
    if ($countItems < $index) {
        continue;
    }
    echo "[$countItems/$totalItems] Process file {$file} " . PHP_EOL;
    $dirName = getCleanFilenameFromFile($file);

    $filesToUpload = array();
    if (!is_dir($file)) {
        $path_parts = pathinfo($file);
        if ($path_parts['extension'] == 'mp4') {
            $filesToUpload[] = $file;
        }
    }
    $totalFilesToUpload = count($filesToUpload);
    for ($filesToUploadCount = 0; $filesToUploadCount < $totalFilesToUpload;) {
        $start1 = microtime(true);
        upload($filesToUpload[$filesToUploadCount]);
        $totalUploadedSizeMb = filesize($filesToUpload[$filesToUploadCount]) / (1024 * 1024);
        $end1 = microtime(true) - $start1;
        if (!empty($end1)) {
            $mbps = number_format($totalUploadedSizeMb / $end1, 1);
        } else {
            $mbps = 0;
        }
        echo "Finished " . number_format($totalUploadedSizeMb, 2) . "MB in " . number_format($end1, 1) . " seconds {$mbps}/mbps" . PHP_EOL;
    }
}

// close the connection



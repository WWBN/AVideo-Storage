<?php

require_once '../configuration.php';
//streamer config
require_once '../functions.php';
function put($folder, $totalSameTime) {
    global $_uploadInfo;
    
    $list = glob("../videos/{$folder}*");
    $totalItems = count($list);
    var_dump($list);exit;
    $filesToUpload = array();
    $totalFilesize = 0;
    $totalBytesTransferred = 0;
    foreach ($list as $value) {
        $filesize = filesize($value['local']['local_path']);
        if ($value['isLocal'] && $filesize > 20) {
            if ($filesize != $value['remote']['remote_filesize']) {
                $filesToUpload[] = $value['local']['local_path'];
                $totalFilesize += $filesize;
            } else {
                error_log("CDNStorage::put same size {$value['remote']['remote_filesize']} {$value['remote']['relative']}");
            }
        } else {
            error_log("CDNStorage::put not valid local file {$value['local']['local_path']}");
        }
    }
    
    
    if (empty($filesToUpload)) {
        error_log("CDNStorage::put videos_id={$videos_id} There is no file to upload ");
        return false;
    }

    $totalFiles = count($filesToUpload);

    error_log("CDNStorage::put videos_id={$videos_id} totalSameTime=$totalSameTime totalFiles={$totalFiles} totalFilesize=" . humanFileSize($totalFilesize));

    $conn_id = array();
    $ret = array();
    $fileUploadCount = 0;
    for ($i = 0; $i < $totalSameTime; $i++) {
        $file = array_shift($filesToUpload);
        //error_log("CDNStorage::put:upload 1 {$i} Start {$file}");
        $upload = uploadToCDNStorage($file, $i, $conn_id, $ret);
        //error_log("CDNStorage::put:upload 1 {$i} done {$file}");
        if ($upload) {
            $fileUploadCount++;
        } else {
            error_log("CDNStorage::put:upload 1 {$i} error {$file}");
        }
    }
    //error_log("CDNStorage::put confirmed " . count($ret));
    $continue = true;
    while ($continue) {
        $continue = false;
        foreach ($ret as $key => $r) {
            if (empty($r)) {
                continue;
            }
            if ($r == FTP_MOREDATA) {
                // Continue uploading...
                $ret[$key] = ftp_nb_continue($conn_id[$key]);
                $continue = true;
            }
            if ($r == FTP_FINISHED) {
                $end = microtime(true) - $_uploadInfo[$key]['microtime'];
                $filesize = $_uploadInfo[$key]['filesize'];
                $remote_file = $_uploadInfo[$key]['remote_file'];
                $humanFilesize = humanFileSize($filesize);
                $ps = humanFileSize($filesize / $end);
                $seconds = number_format($end);
                $ETA = secondsToDuration($end * (($totalFiles - $fileUploadCount) / $totalSameTime));
                $totalBytesTransferred += $filesize;
                unset($ret[$key]);
                unset($_uploadInfo[$key]);

                error_log("CDNStorage::put:uploadToCDNStorage [$key] [{$fileUploadCount}/{$totalFiles}] FTP_FINISHED in {$seconds} seconds {$humanFilesize} {$ps}ps ETA: {$ETA}");

                $file = array_shift($filesToUpload);
                //echo "File finished... $key" . PHP_EOL;
                $upload = uploadToCDNStorage($file, $key, $conn_id, $ret);
                if ($upload) {
                    $fileUploadCount++;
                    $totalBytesTransferred += $filesize;
                } else {
                    error_log("CDNStorage::put:upload 2 {$i} error {$file}");
                }
            }
        }
    }

    error_log("CDNStorage::put videos_id={$videos_id} End totalFiles => $totalFiles, filesCopied => $fileUploadCount, totalBytesTransferred => $totalBytesTransferred");
    // close the connection
    foreach ($conn_id as $value) {
        ftp_close($value);
    }

    if ($fileUploadCount == $totalFiles) {
        createDummyFiles($videos_id);
        sendSocketNotification($videos_id, __('Video upload complete'));
        setProgress($videos_id, true, true);
        error_log("CDNStorage::put finished SUCCESS ");
    } else {
        error_log("CDNStorage::put finished ERROR ");
    }
    return array('filesCopied' => $fileUploadCount, 'totalBytesTransferred' => $totalBytesTransferred);
}

function getConnID($index, &$conn_id) {    
    global $conn_id,$storage_hostname, $storage_username, $storage_password;
    if(empty($conn_id[$index])){
        $conn_id[$index] = ftp_connect($storage_hostname);
        if (empty($conn_id[$index])) {
            echo "getConnID trying again {$index}" . PHP_EOL;
            sleep(1);
            return getConnID($index);
        }
        // login with username and password
        $login_result = ftp_login($conn_id[$index], $storage_username, $storage_password);
        ftp_pasv($conn_id[$index], true);
    }
    return $conn_id[$index];
}

function uploadToCDNStorage($local_path, $index, &$conn_id, &$ret) {
    global $_uploadInfo;
    if (!isset($_uploadInfo)) {
        $_uploadInfo = array();
    }
    if (empty($local_path)) {
        error_log("CDNStorage::put:uploadToCDNStorage error empty local file name {$local_path}");
        return false;
    }
    if (!file_exists($local_path)) {
        error_log("CDNStorage::put:uploadToCDNStorage error file does not exists {$local_path}");
        return false;
    }
    //error_log("CDNStorage::put:uploadToCDNStorage " . __LINE__);
    $remote_file = CDNStorage::filenameToRemotePath($local_path);
    //error_log("CDNStorage::put:uploadToCDNStorage " . __LINE__);
    if (empty($remote_file)) {
        error_log("CDNStorage::put:uploadToCDNStorage error empty remote file name {$local_path}");
        return false;
    }
    $filesize = filesize($local_path);
    //error_log("CDNStorage::put:uploadToCDNStorage [$index] START " . humanFileSize($filesize) . " {$remote_file} ");
    $connID = getConnID($index, $conn_id);
    //error_log("CDNStorage::put:uploadToCDNStorage " . __LINE__);
    $_uploadInfo[$index] = array('microtime' => microtime(true), 'filesize' => $filesize, 'local_path' => $local_path, 'remote_file' => $remote_file);
    //error_log("CDNStorage::put:uploadToCDNStorage " . __LINE__);
    $ret[$index] = ftp_nb_put($connID, $remote_file, $local_path, FTP_BINARY);
    //error_log("CDNStorage::put:uploadToCDNStorage SUCCESS [$index] {$remote_file} " . json_encode($_uploadInfo[$index]));
    return true;
}



$totalSameTime = 5;

$glob = glob("../videos/*");
$totalItems = count($glob);
echo "Found total of {$totalItems} items " . PHP_EOL;
for ($countItems = 0; $countItems < $totalItems;$countItems++) {
    $folder = $glob[$countItems];
    if(is_dir($folder)){
        echo "[{$countItems}/{$totalItems}] Searching {$folder} " . PHP_EOL;
        put($folder, $totalSameTime);
    }
}
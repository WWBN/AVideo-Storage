<?php

require_once '../configuration.php';
//streamer config
require_once '../functions.php';

function put($folder, $totalSameTime) {
    global $_uploadInfo, $conn_id;
    $olderThan = strtotime('2021-11-04');
    $list = glob("{$folder}/*");
    $connID = getConnID(0, $conn_id);
    $filesToUpload = array();
    $totalBytesTransferred = 0;
    foreach ($list as $value) {
        if (is_dir($value)) {
            $listTS = glob("{$value}/*");
            $filename = str_replace('../videos/', '', $value);
            $rawlist = ftp_rawlist($connID, $filename);
            foreach ($rawlist as $file) {
                preg_match('/[-drwx]+ +[0-9] [0-9]+ +[0-9]+ +[0-9]+ +([a-z]{3} [0-9]+ [0-9]+:[0-9]+) (.+)/i', $file, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    $fileTime = strtotime($matches[1]);
                    if ($fileTime < $olderThan) {
                        $f = "{$filename}/$matches[2]";
                        if (preg_match('/.ts$/', $f)) {
                            $filesToUpload[] = $f;
                            echo "Add ($file) $f " . date('Y-m-d H:i:s', $fileTime) . PHP_EOL;
                        }
                    }
                }
            }
        }
    }

    $totalItems = count($filesToUpload);

    if (empty($filesToUpload)) {
        echo ("put $folder There is no file to upload ") . PHP_EOL;
        return false;
    }

    $totalFiles = count($filesToUpload);

    echo ("put $folder totalSameTime=$totalSameTime totalFiles={$totalFiles} ") . PHP_EOL;

    $conn_id = array();
    $ret = array();
    $fileUploadCount = 0;
    for ($i = 0; $i < $totalSameTime; $i++) {
        $file = array_shift($filesToUpload);
//echo ("put:upload 1 {$i} Start {$file}").PHP_EOL;
        $upload = uploadToCDNStorage($file, $i, $conn_id, $ret);
//echo ("put:upload 1 {$i} done {$file}").PHP_EOL;
        if ($upload) {
            $fileUploadCount++;
        } else {
            echo ("put:upload 1 {$i} error {$file}") . PHP_EOL;
        }
    }
//echo ("put confirmed " . count($ret));
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

                echo ("put:uploadToCDNStorage [$key] [{$fileUploadCount}/{$totalFiles}] FTP_FINISHED in {$seconds} seconds {$humanFilesize} {$ps}ps ETA: {$ETA}") . PHP_EOL;

                $file = array_shift($filesToUpload);
//echo "File finished... $key" . PHP_EOL;
                $upload = uploadToCDNStorage($file, $key, $conn_id, $ret);
                if ($upload) {
                    $fileUploadCount++;
                    $totalBytesTransferred += $filesize;
                } else {
                    echo ("put:upload 2 {$i} error {$file}") . PHP_EOL;
                }
            }
        }
    }

    echo ("put End totalFiles => $totalFiles, filesCopied => $fileUploadCount, totalBytesTransferred => $totalBytesTransferred") . PHP_EOL;
// close the connection
    foreach ($conn_id as $value) {
        ftp_close($value);
    }

    if ($fileUploadCount == $totalFiles) {
        createDummyFiles($videos_id);
        sendSocketNotification($videos_id, __('Video upload complete'));
        setProgress($videos_id, true, true);
        echo ("put finished SUCCESS ") . PHP_EOL;
    } else {
        echo ("put finished ERROR ") . PHP_EOL;
    }
    return array('filesCopied' => $fileUploadCount, 'totalBytesTransferred' => $totalBytesTransferred);
}

function getConnID($index, &$conn_id) {
    global $conn_id, $storage_hostname, $storage_username, $storage_password;
    if (empty($conn_id[$index])) {
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
        echo ("put:uploadToCDNStorage error empty local file name {$local_path}") . PHP_EOL;
        return false;
    }
    $local_path = "../videos/{$local_path}";
    if (!file_exists($local_path)) {
        echo ("put:uploadToCDNStorage error file does not exists {$local_path}") . PHP_EOL;
        return false;
    }
//echo ("put:uploadToCDNStorage " . __LINE__).PHP_EOL;
    $remote_file = filenameToRemotePath($local_path);
//echo ("put:uploadToCDNStorage " . __LINE__).PHP_EOL;
    if (empty($remote_file)) {
        echo ("put:uploadToCDNStorage error empty remote file name {$local_path}") . PHP_EOL;
        return false;
    }
    $filesize = filesize($local_path);
//echo ("put:uploadToCDNStorage [$index] START " . humanFileSize($filesize) . " {$remote_file} ").PHP_EOL;
    $connID = getConnID($index, $conn_id);
//echo ("put:uploadToCDNStorage " . __LINE__).PHP_EOL;
    $_uploadInfo[$index] = array('microtime' => microtime(true), 'filesize' => $filesize, 'local_path' => $local_path, 'remote_file' => $remote_file);
//echo ("put:uploadToCDNStorage " . __LINE__).PHP_EOL;
    $ret[$index] = ftp_nb_put($connID, $remote_file, $local_path, FTP_BINARY);
//echo ("put:uploadToCDNStorage SUCCESS [$index] {$remote_file} " . json_encode($_uploadInfo[$index])).PHP_EOL;
    return true;
}

function filenameToRemotePath($filename) {
    global $storage_username;
    $filename = str_replace('../videos/', '', $filename);
    if (!preg_match('/^\/' . $storage_username . '\//', $filename)) {
        return "/{$obj->storage_username}/$filename";
    }
    return $filename;
}

$totalSameTime = 5;
$conn_id = array();
$glob = glob("../videos/*");
$totalItems = count($glob);
echo "Found total of {$totalItems} items " . PHP_EOL;
for ($countItems = 0; $countItems < $totalItems; $countItems++) {
    $folder = $glob[$countItems];
    if (is_dir($folder)) {
        echo "[{$countItems}/{$totalItems}] Searching {$folder} " . PHP_EOL;
        put($folder, $totalSameTime);
    }
}
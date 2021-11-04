<?php

$totalSameTime = 15;

function getRemoteFileName($value) {
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

function upload($value, $index) {
    global $conn_id, $totalBytes, $totalUploadedSize, $ret;
    $remote_file = getRemoteFileName($value);
    if (empty($remote_file)) {
        return false;
    }
    $filesize = filesize($value);
    if (empty($filesize)) {
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] $value empty filesize" . PHP_EOL;
        return false;
    }

    $res = ftp_size($conn_id[$index], $remote_file);
    if ($res > 0) {
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] File $remote_file already exists" . PHP_EOL;
        return false;
    } else {
        $totalBytes += $filesize;
        $totalUploadedSize += $filesize;
        $filesizeMb = $filesize / (1024 * 1024);
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] [$index]Uploading $value to $remote_file " . number_format($filesizeMb, 2) . "MB" . PHP_EOL;
        //ftp_mkdir_recusive($remote_file);

        $ret[$index] = ftp_nb_put($conn_id[$index], $remote_file, $value, FTP_BINARY);
        return true;
    }
}

require_once '../configuration.php';
//streamer config
require_once '../functions.php';


if (!isCommandLineInterface()) {
    return die('Command Line only');
}

$index = intval(@$argv[1]);

$totalSameTimeArg = intval(@$argv[2]);
if (!empty($totalSameTimeArg)) {
    $totalSameTime = $totalSameTimeArg;
}

//$storage_hostname = 'storage.ypt.me';
//$storage_username = '';
//$storage_password = '';
// set up basic connection

$conn_id = array();

for ($i = 0; $i < $totalSameTime; $i++) {
    $conn_id[$i] = ftp_connect($storage_hostname);
    if (empty($conn_id[$i])) {
        unset($conn_id[$i]);
        $totalSameTime = $i + 1;
        break;
    }
    echo "Connection {$i} ... " . PHP_EOL;
    // login with username and password
    $login_result = ftp_login($conn_id[$i], $storage_username, $storage_password);
    ftp_pasv($conn_id[$i], true);
}

$glob = glob("../videos/*");
$totalItems = count($glob);
echo "Found total of {$totalItems} items " . PHP_EOL;
for ($countItems = 0; $countItems < count($glob);) {
    $file = $glob[$countItems];
    $countItems++;
    if ($countItems < $index) {
        continue;
    }
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

    $start = microtime(true);
    $totalBytes = 0;
    $totalFilesToUpload = count($filesToUpload);
    $filesToUploadCount = 0;
    for ($filesToUploadCount = 0; $filesToUploadCount < $totalFilesToUpload;) {
        $start1 = microtime(true);
        $totalUploadedSize = 0;
        $ret = array();
        for ($i = 0; $i < $totalSameTime;) {
            if (empty($filesToUpload[$filesToUploadCount]) || empty($conn_id[$i])) {
                $filesToUploadCount++;
                break;
            }
            $value = $filesToUpload[$filesToUploadCount];
            $filesToUploadCount++;
            
            if(upload($value, $i)){
                $i++;
            }
        }

        $continue = true;
        while ($continue) {
            $continue = false;
            foreach ($ret as $key => $r) {
                if (empty($ret[$key])) {
                    continue;
                }

                if ($ret[$key] == FTP_MOREDATA) {
                    // Continue uploading...
                    $ret[$key] = ftp_nb_continue($conn_id[$key]);
                    $continue = true;
                }
                if ($ret[$key] == FTP_FINISHED) {
                    unset($ret[$key]);

                    $value = $filesToUpload[$filesToUploadCount];
                    $filesToUploadCount++;
                    
                    echo "File finished... $key" . PHP_EOL;
                    upload($value, $key);
                }
            }
        }

        foreach ($ret as $key => $r) {
            if (empty($ret[$key])) {
                continue;
            }
            if ($ret[$key] != FTP_FINISHED) {
                echo "There was an error uploading the file... $key" . PHP_EOL;
                //exit(1);
            }
        }

        $totalUploadedSizeMb = $totalUploadedSize / (1024 * 1024);
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

foreach ($conn_id as $value) {
    ftp_close($value);
}



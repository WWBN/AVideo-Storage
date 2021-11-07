<?php

$totalSameTime = 5;

function findWhereToSkip($filesToUpload, $index) {
    $connID = getConnID($index);
    $totalFiles = count($filesToUpload);
    $lastFile = $filesToUpload[$totalFiles - 1];
    $remote_file = getRemoteFileName($lastFile);
    $res = ftp_size($connID, $remote_file);
    if ($res > 0) {
        return -1;
    }

    for ($i = $index; $i < $totalFiles; $i += 100) {
        if ($i > $totalFiles) {
            $i = $totalFiles;
        }
        $lastFile = $filesToUpload[$i];
        $remote_file = getRemoteFileName($lastFile);
        $res = ftp_size($connID, $remote_file);
        if ($res <= 0) {
            $i -= 100;

            if ($i < 0) {
                $i = 0;
            }

            return $i;
        }
    }
    return $totalFiles;
}

function getConnID($index){
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

function upload($value, $index) {
    global $totalBytes, $totalUploadedSize, $ret, $countItems, $totalItems, $filesToUploadCount, $totalFilesToUpload, $ignoreRemoteCheck;
    $remote_file = getRemoteFileName($value);
    if (empty($remote_file)) {
        return false;
    }
    $filesize = filesize($value);
    if (empty($filesize)) {
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] $value empty filesize" . PHP_EOL;
        return false;
    }
    $connID = getConnID($index);
    if ($ignoreRemoteCheck) {
        $res = -1;
    } else {
        $res = ftp_size($connID, $remote_file);
    }
    if ($res > 0) {
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] File $remote_file already exists [$index]" . PHP_EOL;
        return false;
    } else {
        $totalBytes += $filesize;
        $totalUploadedSize += $filesize;
        $filesizeMb = $filesize / (1024 * 1024);
        echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] [$index]Uploading $value to $remote_file " . number_format($filesizeMb, 2) . "MB" . PHP_EOL;
        //ftp_mkdir_recusive($remote_file);

        $ret[$index] = ftp_nb_put($connID, $remote_file, $value, FTP_BINARY);
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

$ignoreRemoteCheck = $index == -1;
if ($ignoreRemoteCheck) {
    $index = 0;
}

$totalSameTimeArg = intval(@$argv[2]);
if (!empty($totalSameTimeArg)) {
    $totalSameTime = $totalSameTimeArg;
}

//$storage_hostname = 'storage.ypt.me';
//$storage_username = '';
//$storage_password = '';
// set up basic connection

$conn_id = array();

echo "Connect to $storage_hostname MAX {$totalSameTime}" . PHP_EOL;
while (empty($conn_id)) {
    for ($i = 0; $i < $totalSameTime; $i++) {
        $conn = getConnID($i);
        if(empty($conn)){
            $totalSameTime = $i;
            break;
        }else{
            echo "Connection {$i} ... " . PHP_EOL;
        }
    }

    if (empty($conn_id)) {
        echo "ERROR We could not open any connection" . PHP_EOL;
        sleep(5);
    }
}

if(empty($conn_id)){
    die('Could Not Connect');
}

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
    //var_dump($filesToUpload);exit;
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

            if (upload($value, $i)) {
                $i++;
            } else if ($skip) {
                $skip = false;
                $indexFile = findWhereToSkip($filesToUpload, $i);
                if ($indexFile < 0) {
                    echo "1. Finished Go to the next video" . PHP_EOL;
                    continue 3;
                } else {
                    echo "1. Not Finished Go {$indexFile}" . PHP_EOL;
                    $filesToUploadCount = $indexFile;
                }
            }
        }

        $continue = true;
        $skip = true;
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

                    //echo "File finished... $key" . PHP_EOL;
                    $upload = upload($value, $key);
                    if ($skip && !$upload) {
                        $skip = false;
                        $indexFile = findWhereToSkip($filesToUpload, $i);
                        if ($indexFile < 0) {
                            echo "2. Finished Go to the next video" . PHP_EOL;
                            continue 3;
                        } else {
                            echo "2. Not Finished Go {$filesToUploadCount}" . PHP_EOL;
                            $filesToUploadCount = $indexFile;
                        }
                    }
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



<?php

$totalSameTime = 15;

require_once '../configuration.php';
//streamer config
require_once '../functions.php';


if (!isCommandLineInterface()) {
    return die('Command Line only');
}

$index = intval(@$argv[1]);

$totalSameTimeArg = intval(@$argv[2]);
if(!empty($totalSameTimeArg)){
    $totalSameTime = $totalSameTimeArg;
}

//$storage_hostname = 'storage.ypt.me';
//$storage_username = '';
//$storage_password = '';
// set up basic connection

$conn_id = array();

for ($i = 0; $i < $totalSameTime; $i++) {
    $conn_id[$i] = ftp_connect($storage_hostname);
    if(empty($conn_id[$i])){
        unset($conn_id[$i]);
        $totalSameTime = $i+1;
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
    $ret = array();
    for ($filesToUploadCount = 0; $filesToUploadCount < $totalFilesToUpload;) {
        $start1 = microtime(true);
        $totalUploadedSize=0;
        for ($i = 0; $i < $totalSameTime;) {
            if(empty($filesToUpload[$filesToUploadCount]) || empty($conn_id[$i])){
                $filesToUploadCount++;
                break;
            }
            $value = $filesToUpload[$filesToUploadCount];
            $filesToUploadCount++;
            $path_parts = pathinfo($value);
            if (empty($path_parts['extension'])) {
                echo "Skip empty extension {$value}" . PHP_EOL;
                continue;
            }
            if ($path_parts['extension'] == 'tgz') {
                echo "Skip tgz {$value}" . PHP_EOL;
                continue;
            }

            $parts = explode('/videos/', $value);
            $filesize = filesize($value);
            if (empty($filesize)) {
                echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] $value empty filesize" . PHP_EOL;
            }
            $remote_file = "{$dirName}/{$parts[1]}";
            $remote_file = str_replace("{$dirName}/{$dirName}/", "$dirName/", $remote_file);

            $res = ftp_size($conn_id[$i], $remote_file);
            if ($res > 0) {
                echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] File $remote_file already exists" . PHP_EOL;
            } else {
                $totalBytes += $filesize;
                $totalUploadedSize += $filesize;
                $filesizeMb = $filesize / (1024 * 1024);
                echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] [$i]Uploading $value to $remote_file " . number_format($filesizeMb, 2) . "MB" . PHP_EOL;
                //ftp_mkdir_recusive($remote_file);

                $ret[$i] = ftp_nb_put($conn_id[$i], $remote_file, $value, FTP_BINARY);
                $i++;
            }
        }
        foreach ($ret as $key => $r) {
            if (empty($ret[$key])) {
                continue;
            }
            while ($ret[$key] == FTP_MOREDATA) {
                // Continue uploading...
                $ret[$key] = ftp_nb_continue($conn_id[$key]);
            }
            if ($ret[$key] != FTP_FINISHED) {
                echo "There was an error uploading the file... $key" . PHP_EOL;
                //exit(1);
            }else{
                echo "File finished... $key" . PHP_EOL;
            }
        }        
        $totalUploadedSizeMb = $totalUploadedSize / (1024 * 1024);
        $end1 = microtime(true) - $start1;
        if(!empty($end1)){
            $mbps = number_format($totalUploadedSizeMb/$end1,1);
        }else{
            $mbps = 0;
        }
        echo "Finished ".number_format($totalUploadedSizeMb, 2)."MB in ".number_format($end1, 1)." seconds {$mbps}/mbps". PHP_EOL;
    }
}

// close the connection

foreach ($conn_id as $value) {
    ftp_close($value);
}



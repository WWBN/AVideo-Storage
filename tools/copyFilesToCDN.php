<?php

$totalFilesAtTheSameTime = 10;

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

$index = intval(@$argv[1]);

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
    if ($countItems < $index) {
        continue;
    }
    echo "[$countItems/$totalItems] Process file {$file} " . PHP_EOL;
    $dirName = getCleanFilenameFromFile($file);
    // move if there is a subdir wrong (hls files)
    $WrongDirname = "{$dirName}/{$dirName}";
    $is_dir = @ftp_chdir($conn_id, $WrongDirname); //produces warning if file...
    if ($is_dir) {
        echo "Deleting wrong name {$WrongDirname} " . PHP_EOL;
        ftp_chdir($conn_id, '..');
        ftp_chdir($conn_id, '..');
        ftp_rmdir($conn_id, $dirName);
        exit;
    }

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
    //$filesToUploadCount = 0;
    //foreach ($filesToUpload as $value) {
    for ($filesToUploadCount = 0; $filesToUploadCount < count($filesToUpload);) {

        $uploadingNow = array();

        for ($i = 0; $i < $totalFilesAtTheSameTime; $i++) {
            $filesToUploadCount++;
            $value = $filesToUpload[$filesToUploadCount];
            $path_parts = pathinfo($value);
            /*
              if ($path_parts['extension'] == 'mp4') {
              echo "Skip MP4" . PHP_EOL;
              continue;
              }
             * 
             */

            $parts = explode('/videos/', $value);
            $remote_file = "{$dirName}/{$parts[1]}";
            $remote_file = str_replace("{$dirName}/{$dirName}/", "$dirName/", $remote_file);
            $res = ftp_size($conn_id, $remote_file);
            if ($res > 0) {
                echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] File $remote_file already exists" . PHP_EOL;
            } else {
                $filesize = filesize($value);
                $totalBytes += $filesize;
                $filesizeMb = $filesize / (1024 * 1024);
                echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] Uploading $value to $remote_file " . number_format($filesizeMb, 2) . "MB" . PHP_EOL;
                //ftp_mkdir_recusive($remote_file);

                $start1 = microtime(true);
                $d = ftp_nb_put($conn_id, $remote_file, $value, FTP_BINARY);
                /*
                  if (ftp_put($conn_id, $remote_file, $value, FTP_ASCII)) {
                  $end1 = number_format(microtime(true) - $start1, 3);
                  echo "successfully uploaded in {$end1} seconds $value" . PHP_EOL;
                  } else {
                  echo "There was a problem while uploading $file" . PHP_EOL;
                  }
                 * 
                 */
                $uploadingNow[$i] = array('remote_file'=>$remote_file, 'start'=>$start1, 'd'=>$d);
            }
        }
        foreach ($uploadingNow as $value2) {
            while ($value2['d'] == FTP_MOREDATA) {
                // do whatever you want
                // continue uploading
                $value2['d'] = ftp_nb_continue($conn_id);
            }

            if ($value2['d'] != FTP_FINISHED) {
                echo "There was a problem while uploading {$value2['remote_file']}" . PHP_EOL;
            }else{
                $end1 = number_format(microtime(true) - $value2['start'], 3);
                echo "successfully uploaded in {$end1} seconds {$value2['remote_file']}" . PHP_EOL;
            }
        }
    }

    $totalMb = $totalBytes / (1024 * 1024);
    $end = number_format(microtime(true) - $start);
    if (!empty($end)) {
        $ETA = secondsToVideoTime($end * ($totalItems - $countItems));
        echo number_format($totalMb, 2) . "MB Uploaded in " . secondsToVideoTime($end) . ' ' . number_format($totalMb / $end, 1) . "Mbps ETA:{$ETA}" . PHP_EOL;
    }
}

// close the connection
ftp_close($conn_id);

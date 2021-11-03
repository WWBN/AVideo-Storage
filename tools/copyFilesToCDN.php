<?php

$totalSameTime = 10;

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

$conn_id = array();

for ($i = 0; $i < $totalSameTime; $i++) {
    $conn_id[$i] = ftp_connect($storage_hostname);
    // login with username and password
    $login_result = ftp_login($conn_id[$i], $storage_username, $storage_password);
    ftp_pasv($conn_id[$i], true);
}

$glob = glob("../videos/*");
$totalItems = count($glob);
echo "Found total of {$totalItems} items " . PHP_EOL;
for ($countItems = 0; $countItems < count($glob);) {
    $file = $glob[$countItems];
    $ret = array();
    for ($i = 0; $i < $totalSameTime; $i++) {
        $countItems++;
        if ($countItems < $index) {
            continue;
        }
        echo "[$countItems/$totalItems] Process file {$file} " . PHP_EOL;
        $dirName = getCleanFilenameFromFile($file);
        // move if there is a subdir wrong (hls files)
        $WrongDirname = "{$dirName}/{$dirName}";
        $is_dir = @ftp_chdir($conn_id[$i], $WrongDirname); //produces warning if file...
        if ($is_dir) {
            echo "Deleting wrong name {$WrongDirname} " . PHP_EOL;
            ftp_chdir($conn_id[$i], '..');
            ftp_chdir($conn_id[$i], '..');
            ftp_rmdir($conn_id[$i], $dirName);
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
        $filesToUploadCount = 0;
        foreach ($filesToUpload as $value) {
            $filesToUploadCount++;
            $path_parts = pathinfo($value);
            if(empty($path_parts['extension'])){
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
                $filesizeMb = $filesize / (1024 * 1024);
                echo "[$countItems/$totalItems][{$filesToUploadCount}/{$totalFilesToUpload}] Uploading $value to $remote_file " . number_format($filesizeMb, 2) . "MB" . PHP_EOL;
                //ftp_mkdir_recusive($remote_file);

                $start1 = microtime(true);
                $ret[$i] = ftp_nb_put($conn_id[$i], $remote_file, $value, FTP_BINARY);
                $i++;
                if(empty($conn_id[$i])){
                    break;
                }
            }
        }

    }
    for ($i = 0; $i < $totalSameTime; $i++) {
        if (empty($ret[$i])) {
            continue;
        }
        while ($ret[$i] == FTP_MOREDATA) {
            // Continue uploading...
            $ret[$i] = ftp_nb_continue($conn_id[$i]);
        }
        if ($ret[$i] != FTP_FINISHED) {
            echo "There was an error uploading the file... $i";
            //exit(1);
        }
    }
}

// close the connection
//ftp_close($conn_id[$i]);

<?php

//streamer config
require_once '../functions.php';

if (!isCommandLineInterface()) {
    return die('Command Line only');
}

$storage_hostname = 'storage.ypt.me';
$storage_username = '';
$storage_password = '';

// set up basic connection
$conn_id = ftp_connect($storage_hostname);

/*
  // login with username and password
  $login_result = ftp_login($conn_id, $storage_username, $storage_password);
  ftp_pasv($conn_id, true);
 */
$glob = glob("../videos/*");
$totalItems = count($glob);
echo "Found total of {$totalItems} items " . PHP_EOL;
$dirname = $basename . DIRECTORY_SEPARATOR;
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
        //$remote_file = 
        echo "Upload $value to " . PHP_EOL;
    }

    /*
      // upload a file
      if (ftp_mkdir($conn_id, $dir)) {
      echo "successfully created $dir\n";
      if (ftp_put($conn_id, $remote_file, $file, FTP_ASCII)) {
      echo "successfully uploaded $file\n";
      } else {
      echo "There was a problem while uploading $file\n";
      }
      } else {
      echo "There was a problem while creating $dir\n";
      }
     * 
     */
}
/*
// close the connection
ftp_close($conn_id);
 * 
 */

<?php

require_once './configuration.php';
set_time_limit(3600);// 1 hour
header('Content-Type: application/json');
$obj = new stdClass();
$obj->error = true;
$obj->msg = "";
$obj->youPHPTubeStorageURL = $global['youPHPTubeStorageURL'];
$obj->filename = "";
$obj->filesize = 0;

if (empty($_REQUEST['secret']) || $_REQUEST['secret'] !== $global['secret']) {
    $obj->msg = "Invalid secret";
} else if (empty($_REQUEST['filename'])) {
    $obj->msg = "Empty filename";
} else {
    $filename = $_REQUEST['filename'];
    $obj->tarFileName = "{$filename}.tgz";
    $videosDir = "{$global['videos_directory']}";
    $backupDir = "{$videosDir}{$filename}";
    if (is_dir($backupDir)) {
        $backupFile = "{$videosDir}{$obj->tarFileName}";
        $obj->tarURL = "{$global['youPHPTubeStorageURL']}videos/{$obj->tarFileName}";
        $obj->tarFile = $backupFile;
        if (file_exists($obj->tarFile)) {
            $obj->error = false;
            error_log("tarHLS file already exists");
        } else {
            $cmd = "tar -cjf {$backupFile} -C {$backupDir} .";

            error_log("tarHLS Start ({$cmd})");
            //echo $cmd;
            exec($cmd . " 2>&1", $output, $return_val);
            
            if ($return_val === 0) {
                $obj->error = false;
            } else {
                $obj->msg = "Return ERROR ". print_r($return_val, true);
            }
            //$obj->msg = array('output' => implode("<br>", $output), 'return_val' => $return_val, 'success' => $return_val === 0);

            error_log("tarHLS Done (" . json_encode($obj) . ")");
        }
    } else {
        error_log("tarHLS ERROR ({$backupDir}) is NOT a dir ");
    }

    $obj->error = false;
}
if(!$obj->error){
    error_log("tarHLS Finish waiting to complete the file");
    sleep(20);
    $obj->filesize = filesize($obj->tarFile);
}
die(json_encode($obj));
?>

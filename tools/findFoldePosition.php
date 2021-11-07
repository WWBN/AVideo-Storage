<?php


require_once '../configuration.php';
//streamer config
require_once '../functions.php';


if (!isCommandLineInterface()) {
    return die('Command Line only');
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
getConnID(0);
$folder = trim(@$argv[1]);

$list = ftp_rawlist($conn_id[0], "/{$storage_username}/", true);
for ($i=$index;$i<count($list);$i++){
    $value = $list[$i];
    if(preg_match('/'.$folder.'/i', $value)){
        echo $i.' found '.$value.PHP_EOL;
    }
}


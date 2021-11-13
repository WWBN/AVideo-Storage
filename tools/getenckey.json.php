<?php

require_once '../configuration.php';
//streamer config
require_once '../functions.php';


$glob = glob("../videos/{$_REQUEST['folder']}/*");
header('Content-Type: application/json');
$obj = new stdClass();
$obj->folder = $_REQUEST['folder'];
foreach ($glob as $file) {
    if (preg_match('/enc.*.key$/', $file)) {
        
        $obj->file = $file;
        $obj->content = base64_encode(file_get_contents($file));
        $obj->pathinfo = pathinfo($file);
        break;
    }
}
echo json_encode($obj);
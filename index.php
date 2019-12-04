<?php
$configFile = "./configuration.php";
require_once './functions.php';
$exists = false;
if(file_exists($configFile)){
    require_once $configFile;
    require_once './objects/Login.php';
    
    $exists = true;

    $status = url_get_contents($global['aVideoURL'] . 'plugin/YPTStorage/status.php?url='.urlencode($global['aVideoStorageURL']));
    $status = json_decode($status);

    if (!empty($_REQUEST['inputUser']) && !empty($_REQUEST['inputPassword'])) {
        Login::run($_REQUEST['inputUser'], $_REQUEST['inputPassword']);
    }
}else
if(!empty($_POST['inputURL'])){
    if (substr($_POST['inputURL'], -1) !== '/') {
        $_POST['inputURL'] .= "/";
    }
    $url = $_POST['inputURL'] . 'plugin/YPTStorage/addSite.json.php?url='.urlencode(getURLToApplication());
    $status = url_get_contents($url);
    error_log("inputURL: ".$url);
    error_log("inputURL: ".$status);
    $status = json_decode($status);
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

        <title>Storage</title>


        <!-- Bootstrap core CSS -->
        <link href="bootstrap-4.0.0/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>

    </head>

    <body class="text-center">
        <?php
        if(!$exists){
            include './install.php';
        }else if (empty($status) || $status->error) {
            echo "<h3>Your Streamer Answer an error!</h3>";
            if(!empty($status->msg)){
                echo $status->msg;
            }else{
                var_dump($status);
            }
        } else if (!Login::isAdmin()) {
            Login::logoff();
            include './login.php';
        } else {
            include './tabs.php';
        }
        ?>
        <script src="jquery-3.4.1.min.js" type="text/javascript"></script>
        <script src="bootstrap-4.0.0/js/bootstrap.min.js" type="text/javascript"></script>
    </body>
</html>

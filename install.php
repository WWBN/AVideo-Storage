<style>

    .form-signin {
        width: 100%;
        max-width: 330px;
        padding: 15px;
        margin: 0 auto;
    }
    .form-signin .checkbox {
        font-weight: 400;
    }
    .form-signin .form-control {
        position: relative;
        box-sizing: border-box;
        height: auto;
        padding: 10px;
        font-size: 16px;
    }
    .form-signin .form-control:focus {
        z-index: 2;
    }
    .form-signin input[type="email"] {
        margin-bottom: -1px;
        border-bottom-right-radius: 0;
        border-bottom-left-radius: 0;
    }
    .form-signin input[type="password"] {
        margin-bottom: 10px;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
    }
</style>
<?php
$global['videos_directory'] = getPathToApplication() . "videos/";

if (!empty($status) && !empty($status->site->secret)) {
    $data = "<?php

global \$global;

\$global['secret'] = '{$status->site->secret}';
\$global['aVideoStorageURL'] = '{$status->site->url}';
\$global['aVideoURL'] = '" . $_POST['inputURL'] . "';
\$global['videos_directory'] = '{$global['videos_directory']}';

session_name(md5(\$global['aVideoStorageURL']));
session_start();";

    $return = @file_put_contents($configFile, $data);
    if (false || empty($return)) {
        ?>
        <div class="container">
            <div class="alert alert-warning"> We could not create your configuration file. <br> Please create a file (<?php echo getPathToApplication(); ?>configuration.php) with the content below then refresh this page</div>
            <pre style="text-align: left; margin: 10px 0;"><?php
                echo htmlentities($data);
                ?></pre>
        </div>
        <?php
    }
} else {
    ?>
    <div class="container">
        <div class="alert alert-info" role="alert">
            <h4 class="alert-heading">Welcome to the AVideo Storage!</h4>
            Please, make sure you have the YPTStorage plugin to be able to use this server as a storage for your videos.<br>
            You can purchase the plugin <a href="https://www.avideo.com/plugins/">here</a> 
        </div>
        <form class="form-signin" method="post">
            <h1 class="h3 mb-3 font-weight-normal">Please Type Your Streamer URL</h1>
            <label for="inputURL" class="sr-only">Streamer URL</label>
            <input type="url" id="inputURL" name="inputURL" class="form-control" placeholder="URL" required autofocus value="<?php echo @$_POST['inputURL']; ?>">
            <button class="btn btn-lg btn-primary btn-block" type="submit">Register Storage</button>
        </form>
        <?php
        if (!empty($status->msg)) {
            ?>
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading">The streamer send a message to you</h4>
                <?php echo $status->msg; ?> 
            </div>
            <?php
        }
        if (!empty($status) && !empty($status->site->url)) {
            $data = "<?php

global \$global;

\$global['secret'] = '********************************';
\$global['aVideoStorageURL'] = '{$status->site->url}';
\$global['aVideoURL'] = '" . $_POST['inputURL'] . "';
\$global['videos_directory'] = '{$global['videos_directory']}';

session_name(md5(\$global['aVideoStorageURL']));
session_start();";
            ?>

            <div class="alert alert-warning">Please create a file (<?php echo getPathToApplication(); ?>configuration.php) with the content below then refresh this page
            <br> <b>sudo nano <?php echo getPathToApplication(); ?>configuration.php</b></div>
            <pre style="text-align: left; margin: 10px 0;"><?php
                echo htmlentities($data);
                ?></pre>
            <?php
        }
        
        if(!is_dir($global['videos_directory'])){
            if(!mkdir($global['videos_directory'])){
                ?>
            <div class="alert alert-danger">We could not create the directory videos file. you must create it manually<br>
                Please create the following directory:<br> <b>sudo mkdir <?php echo $global['videos_directory']; ?></b></div>
            <?php
            }
        }
        ?>
    </div><?php
}
?>

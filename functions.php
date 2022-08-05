<?php

ini_set('memory_limit', '-1');

if (empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: *');
} else {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

function getPathToApplication() {
    return str_replace("index.php", "", $_SERVER["SCRIPT_FILENAME"]);
}

function getURLToApplication() {
    $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url = explode("index.php", $url);
    $url = $url[0];
    return $url;
}

function getSelfUserAgent() {
    global $global;
    $agent = 'AVideoStorage ';
    $agent .= parse_url($global['aVideoStorageURL'], PHP_URL_HOST);
    
    if(!empty($_SERVER['HTTP_USER_AGENT'])){
        $agent .= " {$_SERVER['HTTP_USER_AGENT']}";
    }
    
    return $agent;
}

function url_get_contents($Url, $ctx = "", $timeout = 0) {
    global $global;
    $agent = getSelfUserAgent();
    if (filter_var($Url, FILTER_VALIDATE_URL)) {

        $session = @$_SESSION;
        session_write_close();
        if (!empty($timeout)) {
            ini_set('default_socket_timeout', $timeout);
        }
    }
    if (empty($ctx)) {
        $opts = array(
            'http' => array('header' => "User-Agent: {$agent}\r\n"),
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
                "allow_self_signed" => true,
            ),
        );
        if (!empty($timeout)) {
            ini_set('default_socket_timeout', $timeout);
            $opts['http'] = array('timeout' => $timeout);
        }
        $context = stream_context_create($opts);
    } else {
        $context = $ctx;
    }
    if (ini_get('allow_url_fopen')) {
        try {
            $tmp = @file_get_contents($Url, false, $context);
            if ($tmp != false) {
                if (filter_var($Url, FILTER_VALIDATE_URL)) {
                    _session_start();
                    $_SESSION = $session;
                }
                return remove_utf8_bom($tmp);
            }
        } catch (ErrorException $e) {
            return "url_get_contents: " . $e->getMessage();
        }
    }
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if (!empty($timeout)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout + 10);
        }
        $output = curl_exec($ch);
        curl_close($ch);
        if (filter_var($Url, FILTER_VALIDATE_URL)) {
            _session_start();
            $_SESSION = $session;
        }
        return remove_utf8_bom($output);
    }
    $result = @file_get_contents($Url, false, $context);
    if (filter_var($Url, FILTER_VALIDATE_URL)) {
        _session_start();
        $_SESSION = $session;
    }
    return remove_utf8_bom($result);
}

function moveFromSiteToLocalHLS($url, $filename, $newTry = 0) {
    global $global;
    $obj = new stdClass();
    $obj->error = true;
    $obj->msg = "";
    $obj->aVideoStorageURL = $global['aVideoStorageURL'];
    $obj->filename = $filename;
    error_log("moveFromSiteToLocalHLS: $url, $filename");
    if (!empty($newTry) || !file_exists($filename) || filesize($filename) < 1000000) { // less then 1 mb
        $wgetResp = wget($url, $filename);
        sleep(10); // wait
    } else {
        error_log("moveFromSiteToLocalHLS: File Exists {$filename}");
        $return_val = 0;
    }
    if (filesize($filename) < 3000) { // less then 1 mb
        error_log("moveFromSiteToLocalHLS: The filesize in the storage is smaller then 300k trying again ");
        file_put_contents($filename, url_get_contents($url));
    }
    //$return_val = file_put_contents($filename, url_get_contents("{$url}"));
    if (filesize($filename) < 3000) { // less then 1 mb
        $obj->msg = "The filesize in the storage is smaller then 300k ";
    } else {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');
        $name2 = pathinfo($url, PATHINFO_FILENAME); //file name without extension
        $directory = "{$global['videos_directory']}{$name2}";
        if (!is_dir($directory)) {
            mkdir($directory);
        }
        error_log("moveFromSiteToLocalHLS: file size is (" . filesize($filename) . ") " . humanFileSize(filesize($filename)));
        $cmd = "tar --overwrite -xf {$filename} -C {$directory}";
        error_log("moveFromSiteToLocalHLS: restoreVideos HLS {$cmd}");
        //echo $cmd;exit;
        exec($cmd . " 2>&1", $output, $return_val);
        if ($return_val === 0) {
            $obj->error = false;
            unlink($filename);
        } else {
            if (empty($newTry) && $newTry < 3) {
                // try again to check if you can get the tar done.
                error_log("0 - moveFromSiteToLocalHLS: fail to unpack, Trying again ($newTry)");
                $newTry += 1;
                sleep($newTry * 10);
                return moveFromSiteToLocalHLS($url, $filename, $newTry);
            }
            $obj->msg = "moveFromSiteToLocalHLS: Error on command {$cmd} ";
            error_log("moveFromSiteToLocalHLS: Error on command {$cmd} " . json_encode($output));
        }
    }

    error_log("moveFromSiteToLocalHLS: Done " . json_encode($obj));
    return $obj;
}

function getFilesizeFromURL($url) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_NOBODY, TRUE);

    $data = curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

    curl_close($ch);
    return $size;
}

function _session_start(Array $options = array()) {
    try {
        if (session_status() == PHP_SESSION_NONE) {
            return session_start($options);
        }
    } catch (Exception $exc) {
        error_log($exc->getTraceAsString());
        return false;
    }
}

function remove_utf8_bom($text) {
    if (strlen($text) > 1000000) {
        return $text;
    }
    $bom = pack('H*', 'EFBBBF');
    $text = preg_replace("/^$bom/", '', $text);
    return $text;
}

function humanFileSize($size, $unit = "") {
    if ((!$unit && $size >= 1 << 30) || $unit == "GB") {
        return number_format($size / (1 << 30), 2) . "GB";
    }

    if ((!$unit && $size >= 1 << 20) || $unit == "MB") {
        return number_format($size / (1 << 20), 2) . "MB";
    }

    if ((!$unit && $size >= 1 << 10) || $unit == "KB") {
        return number_format($size / (1 << 10), 2) . "KB";
    }

    return number_format($size) . " bytes";
}

function getDirSize($dir) {
    if (!is_dir($dir)) {
        $dir = dirname($dir);
    }
    //error_log("getDirSize: start {$dir}");
    $command = "du -sb {$dir}";
    exec($command . " < /dev/null 2>&1", $output, $return_val);
    if ($return_val !== 0) {
        error_log(" getDirSize: ERROR ON Command {$command}");
        return 0;
    } else {
        if (!empty($output[0])) {
            preg_match("/^([0-9]+).*/", $output[0], $matches);
        }
        if (!empty($matches[1])) {
            $size = intval($matches[1]);
            //error_log(" getDirSize: found {$size} from - {$output[0]} " . humanFileSize($size));
            return $size;
        }

        //error_log(" getDirSize: ERROR on pregmatch {$output[0]}");
        return 0;
    }
}

function getUsageFromFilename($filename, $dir = "") {
    global $global;
    $filename = preg_replace("/[^a-z0-9._-]/i", "", $_GET['filename']);
    if (empty($dir)) {
        $dir = $global['videos_directory'];
    }
    $pos = strrpos($dir, '/');
    $dir .= (($pos === false) ? "/" : "");
    $totalSize = 0;
    //error_log("getUsageFromFilename: start {$dir}{$filename}");
    $files = glob("{$dir}{$filename}*");
    foreach ($files as $f) {
        if (is_dir($f)) {
            error_log("getUsageFromFilename: {$f} is Dir");
            $dirSize = getDirSize($f);
            $totalSize += $dirSize;
        } else if (is_file($f)) {
            $filesize = filesize($f);
            error_log("getUsageFromFilename: {$f} is File ({$filesize}) " . humanFileSize($filesize));
            $totalSize += $filesize;
        }
    }
    return $totalSize;
}

function getModifiedTimeFromFilename($filename, $dir = "") {
    global $global;
    $filename = preg_replace("/[^a-z0-9._-]/i", "", $_GET['filename']);
    if (empty($dir)) {
        $dir = $global['videos_directory'];
    }
    $pos = strrpos($dir, '/');
    $dir .= (($pos === false) ? "/" : "");
    if (!file_exists($dir . $filename) && !is_dir($filename)) {
        return 0;
    }
    return filemtime($dir . $filename);
}

/**
 * Returns the size of a file without downloading it, or -1 if the file
 * size could not be determined.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @return The size of the file referenced by $url, or false if the size
 * could not be determined.
 */
function getUsageFromURL($url) {
    global $global;

    if (!empty($global['doNotGetUsageFromURL'])) { // manually add this variable in your configuration.php file to not scan your video usage
        return 0;
    }

    error_log("getUsageFromURL: start ({$url})");
    // Assume failure.
    $result = false;

    $curl = curl_init($url);

    error_log("getUsageFromURL: curl_init ");

    try {
        // Issue a HEAD request and follow any redirects.
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($curl, CURLOPT_USERAGENT, get_user_agent_string());
        $data = curl_exec($curl);
    } catch (Exception $exc) {
        echo $exc->getTraceAsString();
        error_log("getUsageFromURL: ERROR " . $exc->getMessage());
        error_log("getUsageFromURL: ERROR " . curl_errno($curl));
        error_log("getUsageFromURL: ERROR " . curl_error($curl));
    }

    if ($data) {
        error_log("getUsageFromURL: response header " . $data);
        $content_length = "unknown";
        $status = "unknown";

        if (preg_match("/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches)) {
            $status = (int) $matches[1];
        }

        if (preg_match("/Content-Length: (\d+)/", $data, $matches)) {
            $content_length = (int) $matches[1];
        }

        // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
        if ($status == 200 || ($status > 300 && $status <= 308)) {
            $result = $content_length;
        }
    } else {
        error_log("getUsageFromURL: ERROR no response data " . curl_error($curl));
    }

    curl_close($curl);
    return $result;
}

function wget($url, $filename, $try = 0) {
    if (isLocked($url)) {
        $remotesize = getFilesizeFromURL($url);
        error_log("wget: ERROR the url is already downloading $url, $filename remote=($remotesize) local=" . filesize($filename));
        return false;
    }
    lock($url);
    if ($try) {
        $cmd = "wget {$url} -c {$filename}";
    } else {
        $cmd = "wget {$url} -O {$filename}";
    }
    error_log("wget Start ({$cmd}) ");
    //echo $cmd;
    exec($cmd);
    removeLock($url);

    $remotesize = getFilesizeFromURL($url);
    if ($remotesize > filesize($filename) && $try < 5) {
        error_log("wget remote size is bigger then local remote=($remotesize) local=" . filesize($filename));
        return wget($url, $filename, ++$try);
    }
    if (filesize($filename) > 1000000) {
        return true;
    }
    return false;
}

function getLogFile($url) {
    global $global;
    $name = md5($url);
    return "{$global['videos_directory']}lock_{$name}.lock";
}

function lock($url) {
    $file = getLogFile($url);
    return file_put_contents($file, time() . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function removeLock($url) {
    $filename = getLogFile($url);
    if (!file_exists($filename)) {
        return false;
    }
    return unlink($filename);
}

function isLocked($url) {
    $filename = getLogFile($url);
    if (!file_exists($filename)) {
        return false;
    }
    if ((time() - filectime($filename)) < 21600) {// older then 6 hours
        error_log("Locker is too old $filename");
        return false;
    }
    error_log("$url is locked $filename");
    return true;
}

function make_path($path) {
    if (substr($path, -1) !== '/') {
        $path = pathinfo($path, PATHINFO_DIRNAME);
    }
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

function downloadHLS($filepath) {
    global $global;

    if (!file_exists($filepath)) {
        error_log("downloadHLS: file NOT found: {$filepath}");
        return false;
    }
    $output = m3u8ToMP4($filepath);

    if (empty($output)) {
        die("downloadHLS was not possible");
    }

    $outputpath = $output['path'];
    $outputfilename = $output['filename'];

    if (!empty($_REQUEST['title'])) {
        $quoted = sprintf('"%s"', addcslashes(basename($_REQUEST['title']), '"\\'));
    } elseif (!empty($_REQUEST['file'])) {
        $quoted = sprintf('"%s"', addcslashes(basename($_REQUEST['file']), '"\\')) . ".mp4";
    } else {
        $quoted = $outputfilename;
    }

    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=' . $quoted);
    header('Content-Transfer-Encoding: binary');
    header('Connection: Keep-Alive');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header("X-Sendfile: {$outputpath}");
    exit;
}

function playHLSasMP4($filepath) {
    global $global;
    if (!file_exists($filepath)) {
        error_log("playHLSasMP4: file NOT found: {$filepath}");
        return false;
    }
    $output = m3u8ToMP4($filepath);

    if (empty($output)) {
        die("playHLSasMP4 was not possible");
    }

    $outputpath = $output['path'];

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Content-type: video/mp4');
    header('Content-Length: ' . filesize($outputpath));
    header("X-Sendfile: {$outputpath}");
    exit;
}

function m3u8ToMP4($input) {
    global $global;
    $videosDir = "{$global['videos_directory']}";
    $outputfilename = str_replace($videosDir, "", $input);
    $parts = explode("/", $outputfilename);
    $outputfilename = $parts[0] . ".mp4";
    $outputpath = "{$videosDir}cache/downloads/{$outputfilename}";
    make_path($outputpath);
    if (empty($outputfilename)) {
        error_log("downloadHLS: empty outputfilename {$outputfilename}");
        return false;
    }

    $filepath = escapeshellcmd($input);
    $outputpath = escapeshellcmd($outputpath);
    if (!file_exists($outputpath)) {
        $command = get_ffmpeg() . " -allowed_extensions ALL -y -i {$filepath} -c:v copy -c:a copy -bsf:a aac_adtstoasc -strict -2 {$outputpath}";
        //var_dump($outputfilename, $command, $_GET, $filepath, $quoted);exit;
        exec($command . " 2>&1", $output, $return);
        if (!empty($return)) {
            error_log("downloadHLS: ERROR 1 " . implode(PHP_EOL, $output));

            $command = get_ffmpeg() . " -y -i {$filepath} -c:v copy -c:a copy -bsf:a aac_adtstoasc -strict -2 {$outputpath}";
            //var_dump($outputfilename, $command, $_GET, $filepath, $quoted);exit;
            exec($command . " 2>&1", $output, $return);
            if (!empty($return)) {
                error_log("downloadHLS: ERROR 2 " . implode(PHP_EOL, $output));
                return false;
            }
        }
    }
    return array('path' => $outputpath, 'filename' => $outputfilename);
}

function get_ffmpeg($ignoreGPU = false) {
    global $global;
    //return 'ffmpeg -user_agent "'.getSelfUserAgent("FFMPEG").'" ';
    //return 'ffmpeg -headers "User-Agent: '.getSelfUserAgent("FFMPEG").'" ';
    $ffmpeg = 'ffmpeg  ';
    if (empty($ignoreGPU) && !empty($global['ffmpegGPU'])) {
        $ffmpeg .= ' --enable-nvenc ';
    }
    if (!empty($global['ffmpeg'])) {
        $ffmpeg = "{$global['ffmpeg']}{$ffmpeg}";
    }
    return $ffmpeg;
}

function getResolutionFromFilename($filename) {
    global $global;
    $resolution = false;
    if (preg_match("/_([0-9]+).(mp4|webm)/i", $filename, $matches)) {
        if (!empty($matches[1])) {
            $resolution = intval($matches[1]);
        }
    } elseif (preg_match('/res([0-9]+)\/index.m3u8/i', $filename, $matches)) {
        if (!empty($matches[1])) {
            $resolution = intval($matches[1]);
        }
    } elseif (preg_match('/_(HD|Low|SD).(mp4|webm)/i', $filename, $matches)) {
        if (!empty($matches[1])) {
            if ($matches[1] == 'HD') {
                $resolution = 1080;
            } else if ($matches[1] == 'SD') {
                $resolution = 720;
            } else if ($matches[1] == 'Low') {
                $resolution = 480;
            }
        }
    } elseif (preg_match('/\/(hd|low|sd)\/index.m3u8/', $filename, $matches)) {
        if (!empty($matches[1])) {
            if ($matches[1] == 'hd') {
                $resolution = 1080;
            } else if ($matches[1] == 'sd') {
                $resolution = 720;
            } else if ($matches[1] == 'low') {
                $resolution = 480;
            }
        }
    } elseif (preg_match('/video_[0-9_a-z]+\/index.m3u8/i', $filename)) {
        if (file_exists($filename)) {
            $resolution = getHLSHigestResolutionFromFile($filename);
        }
    }
    return $resolution;
}

function getHLSHigestResolutionFromFile($file) {
    global $global;
    $fileOpened = fopen($file, "r");
    $resolution = 0;
    while (!feof($fileOpened)) {
        $line = trim(fgets($fileOpened));
        if (preg_match("/res([^\/]+)\/index.m3u8/", $line, $match)) {
            $newRes = intval($match[1]);
            if (empty($newRes)) {
                continue;
            }
            if ($newRes > $resolution) {
                $resolution = $newRes;
            }
        } else if (preg_match("/^([^\/]+)\/index.m3u8/", $line, $match)) {
            if (empty($match[1])) {
                continue;
            }
            $newRes = strtolower($match[1]);
            switch ($newRes) {
                case "hd":
                    $newRes = 720;
                    break;
                case "sd":
                    $newRes = 540;
                    break;
                case "low":
                    $newRes = 360;
                    break;
                default:
                    $newRes = 0;
                    break;
            }
            if (empty($newRes)) {
                continue;
            }
            if ($newRes > $resolution) {
                $resolution = $newRes;
            }
        }
    }
    fclose($fileOpened);
    return $resolution;
}

function isCommandLineInterface() {
    return (empty($_GET['ignoreCommandLineInterface']) && php_sapi_name() === 'cli');
    }


function getCleanFilenameFromFile($filename) {
    global $global;
    if (empty($filename)) {
        return "";
    }
    $parts = explode('/videos/', $filename);
    $filename = rtrim($parts[1], '/');
    
    if (preg_match('/videos[\/\\\]([^\/\\\]+)[\/\\\].*index.m3u8$/', $filename, $matches)) {
        return $matches[1];
    }
    $search = array('_Low', '_SD', '_HD', '_thumbsV2', '_thumbsSmallV2', '_thumbsSprit', '_roku', '_portrait', '_portrait_thumbsV2', '_portrait_thumbsSmallV2', '_spectrum', '_tvg', '.notfound');

    if (!empty($global['langs_codes_values_withdot']) && is_array($global['langs_codes_values_withdot'])) {
        $search = array_merge($search, $global['langs_codes_values_withdot']);
    }

    if (empty($global['avideo_resolutions']) || !is_array($global['avideo_resolutions'])) {
        $global['avideo_resolutions'] = array(240, 360, 480, 540, 720, 1080, 1440, 2160);
    }

    foreach ($global['avideo_resolutions'] as $value) {
        $search[] = "_{$value}";

        $search[] = "res{$value}";
    }

    $cleanName = str_replace($search, '', $filename);

    if ($cleanName == $filename) {
        $cleanName = preg_replace('/([a-z]+_[0-9]{12}_[a-z0-9]{4})_[0-9]+/', '$1', $filename);
    }

    $path_parts = pathinfo($cleanName);
    if (empty($path_parts['extension'])) {
        //_error_log("Video::getCleanFilenameFromFile could not find extension of ".$filename);
        if (!empty($path_parts['filename'])) {
            return $path_parts['filename'];
        } else {
            return $filename;
        }
    } else if (strlen($path_parts['extension']) > 4) {
        return $cleanName;
    } else if ($path_parts['filename'] == 'index' && $path_parts['extension'] == 'm3u8') {
        $parts = explode(DIRECTORY_SEPARATOR, $cleanName);
        if (!empty($parts[0])) {
            return $parts[0];
        }
        return $parts[1];
    } else {
        return $path_parts['filename'];
    }
}

function secondsToVideoTime($seconds) {
    if (!is_numeric($seconds)) {
        return $seconds;
    }
    $seconds = round($seconds);
    $hours = floor($seconds / 3600);
    $mins = floor($seconds / 60 % 60);
    $secs = floor($seconds % 60);
    return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
}

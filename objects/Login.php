<?php
header('Set-Cookie: cross-site-cookie=name; SameSite=None; Secure');
class Login {

    static function run($user, $pass) {
        global $global;
        $aVideoURL = $global['aVideoURL'];
        if (substr($aVideoURL, -1) !== '/') {
            $aVideoURL .= "/";
        }
        $agent = getSelfUserAgent();

        $postdata = http_build_query(
                array(
                    'user' => $user,
                    'pass' => $pass,
                    'encodedPass' => false
                )
        );

        $opts = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
            'http' => array(
                'method' => 'POST',
                'header' => array(
                    "Content-type: application/x-www-form-urlencoded\r\n",
                    "User-Agent: {$agent}\r\n"),
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);
        
        $url = $aVideoURL . 'login?user='. urlencode($user).'&pass='. urlencode($pass).'&encodedPass='. urlencode($encodedPass);
        $result = @file_get_contents($url, false, $context);
        $result = remove_utf8_bom($result);
        if (empty($result)) {
            $object = new stdClass();
            $object->isLogged = false;
            $object->isAdmin = false;
            $object->canUpload = false;
            $object->canComment = false;
        } else {
            $object = json_decode($result);
        }
        $_SESSION['login'] = $object;
    }

    static function logoff() {
        unset($_SESSION['login']);
    }

    static function isLogged() {
        return !empty($_SESSION['login']->isLogged);
    }

    static function isAdmin() {
        return !empty($_SESSION['login']->isAdmin);
    }

    static function canUpload() {
        return !empty($_SESSION['login']->canUpload);
    }

    static function canComment() {
        return !empty($_SESSION['login']->canComment);
    }


}
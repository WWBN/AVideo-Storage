<?php

class Login {

    static function run($user, $pass) {
        global $global;
        $aVideoURL = $global['aVideoURL'];
        if (substr($aVideoURL, -1) !== '/') {
            $aVideoURL .= "/";
        }

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
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);

        $result = @file_get_contents($aVideoURL . 'login', false, $context);
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

<?php

require_once './configuration.php';
require_once './objects/Login.php';

Login::logoff();

header("location: {$global['youPHPTubeStorageURL']}");
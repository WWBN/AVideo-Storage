<?php

global $global;

$global['secret'] = '66c3859c6ba03a70bf58245b78702696';
$global['youPHPTubeStorageURL'] = 'http://gdrive.local/YouPHPTube-Storage/';
$global['youPHPTubeURL'] = 'http://gdrive.local/YouPHPTube/';
$global['videos_directory'] = '/home/daniel/danielneto.com@gmail.com/htdocs/YouPHPTube-Storage/videos/';

session_name(md5($global['youPHPTubeStorageURL']));
session_start();

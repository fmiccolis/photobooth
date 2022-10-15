<?php
header('Content-Type: application/json');

require_once '../lib/log.php';

$data = $_POST;

// init connection to ftp server
$ftp = ftp_ssl_connect($data['ftp[baseURL]'], $data['ftp[port]']);

// login to ftp server
$login_result = ftp_login($ftp, $data['ftp[username]'], $data['ftp[password]']);

if (!$login_result) {
    logError("Can't connect to FTP Server!");
    die(json_encode("Can't connect to FTP Server!"));
}

die(json_encode('Connected to FTP Server!'));

<?php
header('Content-Type: application/json');

require_once '../lib/db.php';
require_once '../lib/config.php';
require_once '../lib/log.php';

if (empty($_POST['file'])) {
    $errormsg = basename($_SERVER['PHP_SELF']) . ': No file provided';
    logErrorAndDie($errormsg);
}

$file = $_POST['file'];
$filePath = $config['foldersAbs']['images'] . DIRECTORY_SEPARATOR . $file;
$filePathThumb = $config['foldersAbs']['thumbs'] . DIRECTORY_SEPARATOR . $file;
$filePathKeying = $config['foldersAbs']['keying'] . DIRECTORY_SEPARATOR . $file;
$filePathTmp = $config['foldersAbs']['tmp'] . DIRECTORY_SEPARATOR . $file;

if (!unlink($filePath) || !unlink($filePathThumb)) {
    $errormsg = basename($_SERVER['PHP_SELF']) . ': Could not delete file';
    logErrorAndDie($errormsg);
}

if (is_readable($filePathKeying)) {
    if (!unlink($filePathKeying)) {
        $errormsg = basename($_SERVER['PHP_SELF']) . ': Could not delete keying file';
        logErrorAndDie($errormsg);
    }
}

if (!$config['picture']['keep_original']) {
    if (is_readable($filePathTmp)) {
        if (!unlink($filePathTmp)) {
            $errormsg = basename($_SERVER['PHP_SELF']) . ': Could not delete tmp file';
            logErrorAndDie($errormsg);
        }
    }
}

if ($config['database']['enabled']) {
    deleteImageFromDB($file);
}

if ($config['ftp']['enabled'] && $config['ftp']['delete']) {
    $ftp = ftp_ssl_connect($config['ftp']['baseURL'], $config['ftp']['port']);

    // login to ftp server
    $login_result = ftp_login($ftp, $config['ftp']['username'], $config['ftp']['password']);

    if (!$login_result) {
        logErrorAndDie("Can't connect to FTP Server!");
    }

    $remote_dest = empty($config['ftp']['baseFolder']) ? '' : DIRECTORY_SEPARATOR . $config['ftp']['baseFolder'] . DIRECTORY_SEPARATOR;

    $remote_dest .= $config['ftp']['folder'] . DIRECTORY_SEPARATOR . slugify($config['ftp']['title']);
    if ($config['ftp']['appendDate']) {
        $remote_dest .= DIRECTORY_SEPARATOR . date("Y/m/d");
    }

    @cdFTPTree($ftp, $remote_dest);

    $delete_result = ftp_delete($ftp, $file);

    if (!$delete_result) {
        logError("Unable to delete file on ftp server " . $file);
    }

    if ($config['ftp']['upload_thumb']) {
        $delete_result = ftp_delete($ftp, "tmb_" . $file);

        if (!$delete_result) {
            logError("Unable to delete thumb on ftp server " . $file);
        }
    }

    // close the connection
    ftp_close($ftp);
}

echo json_encode([
    'success' => true,
]);

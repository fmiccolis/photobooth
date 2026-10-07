<?php

/** @var array $config */

require_once '../lib/boot.php';

use Photobooth\Enum\FolderEnum;
use Photobooth\Image;
use Photobooth\Rembg;
use Photobooth\Service\LoggerService;

header('Content-Type: application/json');

checkCsrfOrFail($_POST);

$logger = LoggerService::getInstance()->getLogger('main');
$logger->debug(basename($_SERVER['PHP_SELF']));

try {
    if (!$config['rembg']['enabled'] || !$config['rembg']['select_background']) {
        throw new \Exception('Background selection is disabled.');
    }

    $fileName = basename((string) ($_POST['file'] ?? ''));
    if ($fileName === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $fileName)) {
        throw new \Exception('Invalid file name provided');
    }

    // The guest cancelled the background selection: the captured photo stays
    // in the temp folder, only the files prepared for the selection are removed.
    if (($_POST['action'] ?? '') === 'discard') {
        Rembg::deleteCutout($fileName);
        echo json_encode(['success' => true]);
        exit();
    }

    $imageHandler = new Image();
    $imageHandler->debugLevel = $config['dev']['loglevel'];
    Rembg::createCutout($imageHandler, $fileName, $config);

    // The version parameter avoids showing a cached file of a previous photo
    // that had the same name.
    $version = '?v=' . time();
    echo json_encode([
        'success' => true,
        'cutout' => FolderEnum::CUTOUT->public() . '/' . basename(Rembg::getCutoutFile($fileName)) . $version,
        'original' => FolderEnum::CUTOUT->public() . '/' . basename(Rembg::getOriginalPreviewFile($fileName)) . $version,
    ]);
} catch (\Exception $e) {
    $logger->error($e->getMessage(), $_POST);
    echo json_encode(['error' => $e->getMessage()]);
}
exit();

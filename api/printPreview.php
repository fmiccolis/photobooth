<?php

/** @var array $config */

require_once __DIR__ . '/../admin/admin_boot.php';

use Photobooth\Configuration\PhotoboothConfiguration;
use Photobooth\Enum\FolderEnum;
use Photobooth\Image;
use Photobooth\Service\DatabaseManagerService;
use Photobooth\Service\LoggerService;
use Photobooth\Utility\ArrayUtility;
use Photobooth\Utility\PathUtility;
use Photobooth\Utility\PrintImageUtility;
use Symfony\Component\Config\Definition\Processor;

header('Content-Type: application/json');
header('Cache-Control: no-store');

$logger = LoggerService::getInstance()->getLogger('main');
$logger->debug(basename($_SERVER['PHP_SELF']));

checkCsrfOrFail($_POST);

$previewMaxSize = 1200;
$demoImage = 'resources/img/demo/seal-station-norddeich-01.jpg';

try {
    // Apply the (unsaved) values of the admin form on top of the current configuration,
    // only for the sections which define the print layout.
    $data = ArrayUtility::replaceBooleanValues($_POST);
    $override = [];
    foreach (['print', 'textonprint'] as $section) {
        if (isset($data[$section]) && is_array($data[$section])) {
            $override[$section] = ArrayUtility::mergeRecursive($config[$section], $data[$section]);
        }
    }
    if ($override !== []) {
        $processed = (new Processor())->processConfiguration(new PhotoboothConfiguration(), [$override]);
        foreach (array_keys($override) as $section) {
            $config[$section] = $processed[$section];
        }
    }
    if (empty($config['print']['frame'])) {
        $config['print']['frame'] = 'resources/img/frames/frame.png';
    }
    if (empty($config['textonprint']['font'])) {
        $config['textonprint']['font'] = 'resources/fonts/GreatVibes-Regular.ttf';
    }

    // Preview with the latest picture taken, so size and orientation match the real prints.
    $fileName = '';
    $sourceFile = '';
    $images = DatabaseManagerService::getInstance()->getContentFromDB();
    foreach (array_reverse($images) as $image) {
        $candidate = FolderEnum::IMAGES->absolute() . DIRECTORY_SEPARATOR . basename((string) $image);
        if (is_file($candidate)) {
            $fileName = basename($candidate);
            $sourceFile = $candidate;
            break;
        }
    }
    if ($sourceFile === '') {
        $fileName = basename($demoImage);
        $sourceFile = PathUtility::getAbsolutePath($demoImage);
    }

    $imageHandler = new Image();
    $imageHandler->debugLevel = $config['dev']['loglevel'];
    $source = $imageHandler->createFromImage($sourceFile);
    if (!$source instanceof \GdImage) {
        throw new \Exception('Cannot load image ' . $fileName . '.');
    }
    $source = PrintImageUtility::render($imageHandler, $source, $config, $fileName);
    $printWidth = imagesx($source);
    $printHeight = imagesy($source);

    $preview = $source;
    if (max($printWidth, $printHeight) > $previewMaxSize) {
        $preview = $imageHandler->resizeImage($source, $previewMaxSize);
        if (!$preview instanceof \GdImage) {
            throw new \Exception('Cannot resize preview image.');
        }
    }
    unset($source);

    ob_start();
    imagejpeg($preview, null, 85);
    $jpeg = (string) ob_get_clean();
    unset($preview);

    echo json_encode([
        'status' => 'ok',
        'image' => 'data:image/jpeg;base64,' . base64_encode($jpeg),
        'width' => $printWidth,
        'height' => $printHeight,
        'source' => $fileName,
        // Steps which failed silently and would be skipped on the real print as well
        'warnings' => array_values(array_map(static fn ($error): string => is_array($error) ? (string) json_encode($error) : (string) $error, $imageHandler->errorLog)),
    ]);
} catch (\Exception $e) {
    $logger->error('Print preview: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);
}
exit();

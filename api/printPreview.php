<?php

/** @var array $config */

require_once __DIR__ . '/../admin/admin_boot.php';

use Photobooth\Configuration\PhotoboothConfiguration;
use Photobooth\Enum\FolderEnum;
use Photobooth\Enum\ImageFilterEnum;
use Photobooth\Image;
use Photobooth\Service\DatabaseManagerService;
use Photobooth\Service\LoggerService;
use Photobooth\Utility\ArrayUtility;
use Photobooth\Utility\PathUtility;
use Photobooth\Utility\PictureImageUtility;
use Photobooth\Utility\PrintImageUtility;
use Symfony\Component\Config\Definition\Processor;

header('Content-Type: application/json');
header('Cache-Control: no-store');

$logger = LoggerService::getInstance()->getLogger('main');
$logger->debug(basename($_SERVER['PHP_SELF']));

checkCsrfOrFail($_POST);

$previewMaxSize = 1200;
$demoImage = 'resources/img/demo/seal-station-norddeich-01.jpg';
$latestPicturesToCheck = 20;

try {
    // Apply the (unsaved) values of the admin form on top of the current configuration,
    // only for the sections which define how a picture is processed and printed.
    $data = ArrayUtility::replaceBooleanValues($_POST);
    $override = [];
    foreach (['picture', 'textonpicture', 'filters', 'print', 'textonprint'] as $section) {
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
    // Same defaults as ConfigurationService::addDefaults()
    if (empty($config['picture']['frame'])) {
        $config['picture']['frame'] = 'api/randomImg.php?dir=demoframes';
    }
    if (empty($config['textonpicture']['font'])) {
        $config['textonpicture']['font'] = 'resources/fonts/GreatVibes-Regular.ttf';
    }
    if (empty($config['print']['frame'])) {
        $config['print']['frame'] = 'resources/img/frames/frame.png';
    }
    if (empty($config['textonprint']['font'])) {
        $config['textonprint']['font'] = 'resources/fonts/GreatVibes-Regular.ttf';
    }

    // Simulate a new picture: start from the original of the latest picture taken, kept in
    // data/tmp with picture[keep_original], so camera resolution and orientation are the real ones.
    $notes = [];
    $fileName = '';
    $sourceFile = '';
    $sourceType = 'original';
    $images = array_slice(DatabaseManagerService::getInstance()->getContentFromDB(), -$latestPicturesToCheck);
    foreach (array_reverse($images) as $image) {
        $name = basename((string) $image);
        $tmpBase = FolderEnum::TEMP->absolute() . DIRECTORY_SEPARATOR;
        // On collages the temporary file is the collage itself, the first single picture is the original
        foreach ([$tmpBase . substr($name, 0, -4) . '-0.jpg', $tmpBase . $name] as $candidate) {
            if (is_file($candidate)) {
                $fileName = $name;
                $sourceFile = $candidate;
                break 2;
            }
        }
    }
    if ($sourceFile === '') {
        $notes[] = 'demo';
        $sourceType = 'demo';
        $fileName = basename($demoImage);
        $sourceFile = PathUtility::getAbsolutePath($demoImage);
    }
    if ($config['rembg']['enabled']) {
        $notes[] = 'rembg';
    }

    $imageHandler = new Image();
    $imageHandler->debugLevel = $config['dev']['loglevel'];
    $source = $imageHandler->createFromImage($sourceFile);
    if (!$source instanceof \GdImage) {
        throw new \Exception('Cannot load image ' . $fileName . '.');
    }
    // The guest interface always sends the default filter when taking a picture
    $filter = $config['filters']['defaults'];
    if (!$filter instanceof ImageFilterEnum) {
        $filter = ImageFilterEnum::tryFrom((string) $filter);
    }
    $source = PictureImageUtility::render($imageHandler, $source, $config, $filter);
    $source = PrintImageUtility::render($imageHandler, $source, $config, $fileName);
    $printWidth = imagesx($source);
    $printHeight = imagesy($source);

    // Area of the QR code in percent of the print, used to drag it on the preview
    $qr = null;
    if ($config['print']['qrcode'] && $imageHandler->qrBox !== []) {
        $box = $imageHandler->qrBox;
        $qr = [
            'x' => $box['x'] / $printWidth * 100,
            'y' => $box['y'] / $printHeight * 100,
            'width' => $box['width'] / $printWidth * 100,
            'height' => $box['height'] / $printHeight * 100,
            'scale' => $box['width'] / min($printWidth, $printHeight) * 100,
            'minScale' => PrintImageUtility::QR_MIN_PIXEL_SIZE / min($printWidth, $printHeight) * 100,
        ];
    }

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
        'sourceType' => $sourceType,
        'notes' => $notes,
        'qr' => $qr,
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

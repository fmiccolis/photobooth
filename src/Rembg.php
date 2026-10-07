<?php

namespace Photobooth;

use Photobooth\Enum\FolderEnum;
use Photobooth\Logger\NamedLogger;
use Photobooth\Service\LoggerService;
use Photobooth\Utility\ImageUtility;
use Photobooth\Utility\PathUtility;

class Rembg
{
    /**
     * Folders holding the backgrounds a guest can choose from after taking a
     * photo. The first folder that contains images is used.
     */
    public const SELECTABLE_BACKGROUND_FOLDERS = [
        'private/images/keyingBackgrounds',
        'resources/img/background',
    ];

    /**
     * Value of the background selection meaning "keep the original photo".
     */
    public const BACKGROUND_ORIGINAL = 'original';

    public static function process(
        Image $imageHandler,
        array $vars,
        array $rembgConfig,
        \GdImage $imageResource
    ): array {
        $logger = self::getLogger();
        // Only process if rembg is enabled and not in collage/chroma mode
        if (
            empty($rembgConfig['enabled']) ||
            !empty($vars['isCollage']) ||
            !empty($vars['isChroma'])
        ) {
            $logger->debug('Skipped (disabled or collage/chroma mode)');
            return [$imageHandler, $imageResource];
        }

        $logger->debug('Starting background removal process via service');

        try {
            $processedImage = self::removeBackground($imageResource, $rembgConfig);
        } catch (\Exception $e) {
            $logger->error('Processing failed: ' . $e->getMessage());
            return [$imageHandler, $imageResource]; // Fallback to original image
        }

        // Apply background if configured
        if (!empty($rembgConfig['background'])) {
            $backgroundPath = PathUtility::getAbsolutePath($rembgConfig['background']);
            if (file_exists($backgroundPath)) {
                try {
                    $processedImage = self::applyBackgroundImage($processedImage, $backgroundPath, $rembgConfig['backgroundMode'] ?? 'scale-fill');
                    $logger->debug('Background image applied after rembg processing');
                } catch (\Exception $e) {
                    $logger->error($e->getMessage());
                }
            }
        }

        $logger->debug('Background removal applied successfully via service');

        return [$imageHandler, $processedImage];
    }

    /**
     * Send an image to the rembg service and return the cut out subject with a
     * transparent background.
     *
     * @throws \Exception when the service is not reachable or returns no image
     */
    public static function removeBackground(\GdImage $imageResource, array $rembgConfig): \GdImage
    {
        $logger = self::getLogger();
        $tempInput = false;

        try {
            // Prepare temporary file. Use the file created by tempnam() itself, so
            // no empty placeholder is left behind in the temp folder.
            $tempInput = tempnam(sys_get_temp_dir(), 'rembg_input_');
            if ($tempInput === false) {
                throw new \Exception('Failed to create temporary input file');
            }

            // Save image for upload. Use the lowest zlib compression level: the
            // file only travels to the local rembg service, and the default level
            // takes several seconds for a full-size photo on a Raspberry Pi.
            if (!imagepng($imageResource, $tempInput, 1)) {
                throw new \Exception('Failed to save input image');
            }

            // Prepare API URL and parameters
            // The rembg server reads the options of a POST request from the form
            // fields only: query parameters are silently ignored and the server
            // falls back to its default model (bria-rmbg), which is far slower.
            $apiUrl = 'http://localhost:7000/api/remove';
            $formParams = [];
            if (!empty($rembgConfig['model'])) {
                $formParams['model'] = $rembgConfig['model'];
            }
            if (!empty($rembgConfig['alpha_matting'])) {
                $formParams['a'] = 'true';
                if (!empty($rembgConfig['alpha_matting_background_threshold'])) {
                    $formParams['ab'] = (string) $rembgConfig['alpha_matting_background_threshold'];
                }
                if (!empty($rembgConfig['alpha_matting_erode_size'])) {
                    $formParams['ae'] = (string) $rembgConfig['alpha_matting_erode_size'];
                }
                if (!empty($rembgConfig['alpha_matting_foreground_threshold'])) {
                    $formParams['af'] = (string) $rembgConfig['alpha_matting_foreground_threshold'];
                }
            }
            if (!empty($rembgConfig['post_processing'])) {
                $formParams['ppm'] = 'true';
            }

            // Log: Image sent to API + parameters
            $paramString = json_encode($formParams);
            $logger->debug("Image sent to API: $apiUrl with parameters: $paramString");

            // cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge([
                'file' => new \CURLFile($tempInput, 'image/png', 'input.png')
            ], $formParams));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Log: Image returned + processing success/failure
            if ($error) {
                $logger->error("Image processing failed: cURL error - $error");
                throw new \Exception('cURL error: ' . $error);
            }
            if ($httpCode !== 200) {
                $responsePreview = is_string($response) ? substr($response, 0, 200) : 'no response';
                $logger->error("Image processing failed: HTTP $httpCode - Response: " . $responsePreview);
                throw new \Exception('API error: HTTP ' . $httpCode . ' - ' . $responsePreview);
            }

            // Check MIME type
            if (!is_string($response)) {
                throw new \Exception('Invalid API response: expected string');
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                throw new \Exception('Failed to initialize fileinfo');
            }
            $mimeType = finfo_buffer($finfo, $response);
            finfo_close($finfo);
            if ($mimeType === false || !str_starts_with($mimeType, 'image/')) {
                $mimeDisplay = $mimeType !== false ? $mimeType : 'unknown';
                $logger->error("Invalid response: Expected image, got $mimeDisplay - Response: " . substr($response, 0, 200));
                throw new \Exception('Invalid API response: not an image');
            }

            $logger->debug("Image successfully processed and returned from API (HTTP 200, MIME: $mimeType)");

            // Load processed image
            $processedImage = imagecreatefromstring($response);
            if ($processedImage === false) {
                throw new \Exception('Failed to load processed image');
            }

            return $processedImage;
        } finally {
            if ($tempInput !== false && file_exists($tempInput)) {
                unlink($tempInput);
            }
        }
    }

    /**
     * Put a background image behind a transparent foreground.
     *
     * @throws \Exception when the background image can't be loaded
     */
    public static function applyBackgroundImage(\GdImage $foreground, string $backgroundPath, string $mode): \GdImage
    {
        $backgroundContent = file_get_contents($backgroundPath);
        if ($backgroundContent === false) {
            throw new \Exception('Failed to read background image file');
        }
        $backgroundImage = imagecreatefromstring($backgroundContent);
        if ($backgroundImage === false) {
            throw new \Exception('Failed to load background image');
        }

        return self::applyBackgroundWithMode($foreground, $backgroundImage, $mode, self::getLogger());
    }

    /**
     * Backgrounds a guest can choose from, as paths relative to the Photobooth
     * root (e.g. "private/images/keyingBackgrounds/beach.jpg").
     *
     * @return string[]
     */
    public static function getSelectableBackgrounds(): array
    {
        foreach (self::SELECTABLE_BACKGROUND_FOLDERS as $folder) {
            try {
                $files = ImageUtility::getImagesFromPath($folder);
            } catch (\Exception $e) {
                continue;
            }
            if (count($files) === 0) {
                continue;
            }
            $backgrounds = array_map(fn (string $file): string => $folder . '/' . basename($file), $files);
            sort($backgrounds, SORT_NATURAL | SORT_FLAG_CASE);

            return $backgrounds;
        }

        return [];
    }

    public static function isSelectableBackground(string $background): bool
    {
        return in_array($background, self::getSelectableBackgrounds(), true);
    }

    /**
     * Cut out subject of a captured photo (PNG with transparency), already
     * flipped and rotated like the final picture.
     */
    public static function getCutoutFile(string $fileName): string
    {
        return FolderEnum::CUTOUT->absolute() . DIRECTORY_SEPARATOR . pathinfo($fileName, PATHINFO_FILENAME) . '.png';
    }

    /**
     * The captured photo flipped and rotated like the final picture, used to
     * preview the "original" choice.
     */
    public static function getOriginalPreviewFile(string $fileName): string
    {
        return FolderEnum::CUTOUT->absolute() . DIRECTORY_SEPARATOR . pathinfo($fileName, PATHINFO_FILENAME) . '.jpg';
    }

    /**
     * Prepare the files needed to let the guest choose a background for a
     * captured photo: the oriented original and the cut out subject.
     *
     * @throws \Exception when the photo can't be processed or rembg fails
     */
    public static function createCutout(Image $imageHandler, string $fileName, array $config): void
    {
        self::purgeCutouts();

        $tmpFile = FolderEnum::TEMP->absolute() . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($tmpFile)) {
            throw new \Exception('Image doesn\'t exist.');
        }

        $imageResource = $imageHandler->createFromImage($tmpFile);
        if (!$imageResource instanceof \GdImage) {
            throw new \Exception('Error creating image resource.');
        }
        $imageResource = $imageHandler->applyOrientation(
            $imageResource,
            (string) $config['picture']['flip'],
            (int) $config['picture']['rotation']
        );

        if (!imagejpeg($imageResource, self::getOriginalPreviewFile($fileName), 90)) {
            throw new \Exception('Failed to save original preview.');
        }

        try {
            $cutout = self::removeBackground($imageResource, $config['rembg']);
            imagesavealpha($cutout, true);
            if (!imagepng($cutout, self::getCutoutFile($fileName), 1)) {
                throw new \Exception('Failed to save cutout.');
            }
        } catch (\Exception $e) {
            // don't leave the original preview behind when the selection can't be offered
            self::deleteCutout($fileName);
            throw $e;
        }
    }

    /**
     * Remove the files created by createCutout().
     */
    public static function deleteCutout(string $fileName): void
    {
        foreach ([self::getCutoutFile($fileName), self::getOriginalPreviewFile($fileName)] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Remove files created by createCutout() that are older than the given
     * age. They are only needed while the guest chooses the background or
     * changes the filter of the result, so they must not pile up during an
     * event.
     */
    public static function purgeCutouts(int $maxAgeSeconds = 1800): void
    {
        $files = glob(FolderEnum::CUTOUT->absolute() . DIRECTORY_SEPARATOR . '*');
        if ($files === false) {
            return;
        }
        $limit = time() - $maxAgeSeconds;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $modified = filemtime($file);
            if ($modified !== false && $modified < $limit) {
                unlink($file);
            }
        }
    }

    private static function getLogger(): NamedLogger
    {
        return LoggerService::getInstance()->getLogger('rembg');
    }

    /**
     * Apply background image with different scaling/cropping modes
     *
     * @param \GdImage $foreground The transparent foreground image
     * @param \GdImage $background The background image
     * @param string $mode The mode: 'none', 'scale-fit', 'scale-fill', 'crop-center', 'stretch'
     * @param \Photobooth\Logger\NamedLogger $logger Logger instance
     * @return \GdImage The composited image
     */
    private static function applyBackgroundWithMode(
        \GdImage $foreground,
        \GdImage $background,
        string $mode,
        \Photobooth\Logger\NamedLogger $logger
    ): \GdImage {
        $canvasWidth = imagesx($foreground);
        $canvasHeight = imagesy($foreground);
        $bgWidth = imagesx($background);
        $bgHeight = imagesy($background);

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

        $logger->debug("Applying background with mode: {$mode} (Canvas: {$canvasWidth}x{$canvasHeight}, BG: {$bgWidth}x{$bgHeight})");

        switch ($mode) {
            case 'none':
                // Original behavior: direct copy, no scaling
                imagecopy($canvas, $background, 0, 0, 0, 0, $bgWidth, $bgHeight);
                break;

            case 'scale-fit':
                // Scale background to fit inside canvas (preserve aspect ratio, may have black bars)
                $scale = min($canvasWidth / $bgWidth, $canvasHeight / $bgHeight);
                $scaledWidth = (int)($bgWidth * $scale);
                $scaledHeight = (int)($bgHeight * $scale);
                $offsetX = (int)(($canvasWidth - $scaledWidth) / 2);
                $offsetY = (int)(($canvasHeight - $scaledHeight) / 2);

                $black = imagecolorallocate($canvas, 0, 0, 0);
                if ($black !== false) {
                    imagefill($canvas, 0, 0, $black);
                }
                imagecopyresampled(
                    $canvas,
                    $background,
                    $offsetX,
                    $offsetY,
                    0,
                    0,
                    $scaledWidth,
                    $scaledHeight,
                    $bgWidth,
                    $bgHeight
                );
                break;

            case 'scale-fill':
                // Scale background to cover entire canvas (preserve aspect ratio, may crop)
                $scale = max($canvasWidth / $bgWidth, $canvasHeight / $bgHeight);
                $scaledWidth = (int)($bgWidth * $scale);
                $scaledHeight = (int)($bgHeight * $scale);
                $offsetX = (int)(($canvasWidth - $scaledWidth) / 2);
                $offsetY = (int)(($canvasHeight - $scaledHeight) / 2);

                imagecopyresampled(
                    $canvas,
                    $background,
                    $offsetX,
                    $offsetY,
                    0,
                    0,
                    $scaledWidth,
                    $scaledHeight,
                    $bgWidth,
                    $bgHeight
                );
                break;

            case 'crop-center':
                // Crop background from center to match canvas size
                if ($bgWidth < $canvasWidth || $bgHeight < $canvasHeight) {
                    // Background too small, scale up first
                    $scale = max($canvasWidth / $bgWidth, $canvasHeight / $bgHeight);
                    $scaledBg = imagecreatetruecolor((int)($bgWidth * $scale), (int)($bgHeight * $scale));
                    imagecopyresampled($scaledBg, $background, 0, 0, 0, 0, imagesx($scaledBg), imagesy($scaledBg), $bgWidth, $bgHeight);
                    $cropX = (int)((imagesx($scaledBg) - $canvasWidth) / 2);
                    $cropY = (int)((imagesy($scaledBg) - $canvasHeight) / 2);
                    imagecopy($canvas, $scaledBg, 0, 0, $cropX, $cropY, $canvasWidth, $canvasHeight);
                    imagedestroy($scaledBg);
                } else {
                    $cropX = (int)(($bgWidth - $canvasWidth) / 2);
                    $cropY = (int)(($bgHeight - $canvasHeight) / 2);
                    imagecopy($canvas, $background, 0, 0, $cropX, $cropY, $canvasWidth, $canvasHeight);
                }
                break;

            case 'stretch':
            default:
                // Stretch background to exact canvas size (may distort)
                imagecopyresampled($canvas, $background, 0, 0, 0, 0, $canvasWidth, $canvasHeight, $bgWidth, $bgHeight);
                break;
        }

        // Merge transparent foreground onto background
        imagecopy($canvas, $foreground, 0, 0, 0, 0, $canvasWidth, $canvasHeight);

        return $canvas;
    }
}

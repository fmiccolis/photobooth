<?php

namespace Photobooth\Utility;

use GdImage;
use Photobooth\Image;

/**
 * Builds the image that is sent to the printer (rotation, crop, frame, QR code and text).
 * Shared by the print API and the print preview in the admin panel so both always match.
 */
class PrintImageUtility
{
    /**
     * Smallest QR code size in pixels for the 'custom' position: about 2 pixels per module for the
     * usual URLs (33-41 modules), below it phones can't scan it anymore.
     */
    public const QR_MIN_PIXEL_SIZE = 100;

    /**
     * Applies the print layout configured in the print and textonprint sections to the source image.
     */
    public static function render(Image $imageHandler, GdImage $source, array $config, string $fileName): GdImage
    {
        // rotate image if needed
        if (imagesx($source) > imagesy($source) || $config['print']['no_rotate'] === true) {
            $imageHandler->qrRotate = false;
        } else {
            $source = imagerotate($source, 90, 0);
            $imageHandler->qrRotate = true;
            if (!$source) {
                throw new \Exception('Cannot rotate image resource.');
            }
        }

        // Crop first, so frame, QR code and text are placed on the final print size and never cut off
        if ($config['print']['crop']) {
            $source = $imageHandler->resizeCropImage($source, $config['print']['crop_width'], $config['print']['crop_height']);
            if (!$source instanceof GdImage) {
                throw new \Exception('Failed to crop image resource.');
            }
        }

        if ($config['print']['print_frame']) {
            $imageHandler->framePath = $config['print']['frame'];
            $imageHandler->frameExtend = false;
            $source = $imageHandler->applyFrame($source);
            if (!$source instanceof GdImage) {
                throw new \Exception('Failed to apply frame to image resource.');
            }
        }

        if ($config['print']['qrcode']) {
            $imageHandler->qrUrl = QrCodeUtility::getUrl($config, $fileName);
            $imageHandler->qrSize = (int) $config['print']['qrSize'];
            $imageHandler->qrMargin = (int) $config['print']['qrMargin'];
            $imageHandler->qrColor = $config['print']['qrBgColor'];
            $imageHandler->qrOffset = (int) $config['print']['qrOffset'];
            $imageHandler->qrPosition = $config['print']['qrPosition'];
            $imageHandler->qrPixelSize = 0;
            if ($config['print']['qrPosition'] === 'custom') {
                // Position and size relative to the print, so they work with any camera resolution
                $shortSide = min(imagesx($source), imagesy($source));
                $imageHandler->qrPixelSize = max(self::QR_MIN_PIXEL_SIZE, (int) round($shortSide * (float) $config['print']['qrScale'] / 100));
                $imageHandler->qrX = (float) $config['print']['qrX'];
                $imageHandler->qrY = (float) $config['print']['qrY'];
            }

            $qrCode = $imageHandler->createQr();
            if (!$qrCode instanceof GdImage) {
                throw new \Exception('Cannot create QR Code resource.');
            }
            $source = $imageHandler->applyQr($qrCode, $source);
            if (!$source instanceof GdImage) {
                throw new \Exception('Cannot apply QR Code to image resource.');
            }
            unset($qrCode);
        }

        if ($config['textonprint']['enabled']) {
            $imageHandler->fontSize = $config['textonprint']['font_size'];
            $imageHandler->fontRotation = $config['textonprint']['rotation'];
            $imageHandler->fontLocationX = $config['textonprint']['locationx'];
            $imageHandler->fontLocationY = $config['textonprint']['locationy'];
            $imageHandler->fontColor = $config['textonprint']['font_color'];
            $imageHandler->fontPath = $config['textonprint']['font'];
            $imageHandler->textLine1 = $config['textonprint']['line1'];
            $imageHandler->textLine2 = $config['textonprint']['line2'];
            $imageHandler->textLine3 = $config['textonprint']['line3'];
            $imageHandler->textLineSpacing = $config['textonprint']['linespace'];

            $source = $imageHandler->applyText($source);
            if (!$source instanceof GdImage) {
                throw new \Exception('Failed to apply text to image resource.');
            }
        }

        return $source;
    }
}

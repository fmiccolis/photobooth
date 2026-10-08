<?php

namespace Photobooth\Utility;

use GdImage;
use Photobooth\Enum\ImageFilterEnum;
use Photobooth\Image;

/**
 * Steps applied to a picture after it has been taken (see api/applyEffects.php).
 * Shared with the print preview in the admin panel, which simulates a new picture with the current settings.
 */
class PictureImageUtility
{
    public static function applyPolaroid(Image $imageHandler, GdImage $imageResource, array $config): GdImage
    {
        if (!$config['picture']['polaroid_effect']) {
            return $imageResource;
        }

        $imageHandler->polaroidRotation = $config['picture']['polaroid_rotation'];
        $imageResource = $imageHandler->effectPolaroid($imageResource);
        if (!$imageResource instanceof GdImage) {
            throw new \Exception('Error applying polaroid effect.');
        }

        return $imageResource;
    }

    /**
     * Applies the picture frame (or the collage frame on single collage pictures) if enabled.
     * $imageHandler->framePath has to be set by the caller.
     */
    public static function applyFrame(Image $imageHandler, GdImage $imageResource, array $config, bool $isCollage, bool $editSingleCollage): GdImage
    {
        if (!(($config['picture']['take_frame'] && !$isCollage) || ($editSingleCollage && ($config['collage']['take_frame'] === 'always' || $config['collage']['take_frame'] !== 'always' && $config['picture']['take_frame'])))) {
            return $imageResource;
        }

        if (!$isCollage || $config['collage']['take_frame'] !== 'always') {
            $imageHandler->frameExtend = $config['picture']['extend_by_frame'];
            if ($config['picture']['extend_by_frame']) {
                $imageHandler->frameExtendLeft = $config['picture']['frame_left_percentage'];
                $imageHandler->frameExtendRight = $config['picture']['frame_right_percentage'];
                $imageHandler->frameExtendBottom = $config['picture']['frame_bottom_percentage'];
                $imageHandler->frameExtendTop = $config['picture']['frame_top_percentage'];
            }
        } else {
            $imageHandler->frameExtend = false;
        }
        $imageResource = $imageHandler->applyFrame($imageResource);
        if (!$imageResource instanceof GdImage) {
            throw new \Exception('Error applying frame to image resource.');
        }

        return $imageResource;
    }

    /**
     * Applies the text on picture, $scale compensates a downscale done before applying the filter.
     */
    public static function applyText(Image $imageHandler, GdImage $imageResource, array $config, float $scale): GdImage
    {
        // Cast after scaling to avoid implicit float-to-int deprecation warnings in PHP 8.4
        $imageHandler->fontSize        = (int) round($config['textonpicture']['font_size'] * $scale);
        $imageHandler->textLineSpacing = (int) round($config['textonpicture']['linespace'] * $scale);
        $imageHandler->fontLocationX   = (int) round($config['textonpicture']['locationx'] * $scale);
        $imageHandler->fontLocationY   = (int) round($config['textonpicture']['locationy'] * $scale);
        $imageHandler->fontRotation = $config['textonpicture']['rotation'];
        $imageHandler->fontColor = $config['textonpicture']['font_color'];
        $imageHandler->fontPath = $config['textonpicture']['font'];
        $imageHandler->textLine1 = $config['textonpicture']['line1'];
        $imageHandler->textLine2 = $config['textonpicture']['line2'];
        $imageHandler->textLine3 = $config['textonpicture']['line3'];
        $imageResource = $imageHandler->applyText($imageResource);
        if (!$imageResource instanceof GdImage) {
            throw new \Exception('Error applying text to image resource.');
        }

        return $imageResource;
    }

    /**
     * Simulates a single picture taken with the current settings, like api/applyEffects.php does
     * for the 'photo' style. Background removal (rembg) is not simulated.
     */
    public static function render(Image $imageHandler, GdImage $imageResource, array $config, ?ImageFilterEnum $imageFilter): GdImage
    {
        $imageHandler->framePath = PathUtility::getPublicPath($config['picture']['frame']);

        $originalWidth = null;
        if ($imageFilter !== ImageFilterEnum::PLAIN || $config['rembg']['enabled']) {
            $originalWidth = imagesx($imageResource);
            $originalHeight = imagesy($imageResource);
            $filterProcessSize = intval($config['filters']['process_size'] ?? 0);
            if ($filterProcessSize > 0 && ($originalWidth > $filterProcessSize || $originalHeight > $filterProcessSize)) {
                $downscaledResource = $imageHandler->resizeImage($imageResource, $filterProcessSize);
                if ($downscaledResource instanceof GdImage) {
                    $imageResource = $downscaledResource;
                }
            }
        }

        if ($imageFilter !== null && $imageFilter !== ImageFilterEnum::PLAIN) {
            ImageUtility::applyFilter($imageFilter, $imageResource);
        }

        $imageResource = $imageHandler->applyOrientation(
            $imageResource,
            (string) $config['picture']['flip'],
            (int) $config['picture']['rotation']
        );
        $imageResource = self::applyPolaroid($imageHandler, $imageResource, $config);
        $imageResource = self::applyFrame($imageHandler, $imageResource, $config, false, false);

        if ($config['textonpicture']['enabled']) {
            $scale = $originalWidth !== null ? imagesx($imageResource) / $originalWidth : 1.0;
            $imageResource = self::applyText($imageHandler, $imageResource, $config, $scale);
        }

        return $imageResource;
    }
}

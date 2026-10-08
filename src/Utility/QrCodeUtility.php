<?php

namespace Photobooth\Utility;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Margin\Margin;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Photobooth\Service\RemoteStorageService;

class QrCodeUtility
{
    /**
     * Returns the URL encoded into the QR code for the given image.
     */
    public static function getUrl(array $config, string $fileName): string
    {
        $url = $config['qr']['url'];
        if ($config['ftp']['enabled'] && $config['ftp']['useForQr']) {
            $remoteStorageService = RemoteStorageService::getInstance();
            $url = $remoteStorageService->getWebpageUri();
            if ($config['qr']['append_filename']) {
                $url .= '/?photo=';
            }
        }
        if ($config['qr']['append_filename']) {
            $url .= $fileName;
        }

        return PathUtility::getPublicPath($url, true);
    }

    public static function create(string $text, string $labelText = '', int $size = 300, int $margin = 15, RoundBlockSizeMode $roundBlockSizeMode = RoundBlockSizeMode::Margin): ResultInterface
    {
        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size - (2 * $margin),
            margin: $margin,
            roundBlockSizeMode: $roundBlockSizeMode,
            labelText: $labelText,
            labelMargin: new Margin(0, $margin, $margin, $margin)
        );

        $result = $builder->build();

        return $result;
    }
}

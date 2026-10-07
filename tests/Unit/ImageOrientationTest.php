<?php

namespace Photobooth\Tests\Unit;

use Photobooth\Image;
use PHPUnit\Framework\TestCase;

final class ImageOrientationTest extends TestCase
{
    /**
     * 4x2 white image with a red marker in the top left corner.
     */
    private function createMarkedImage(): \GdImage
    {
        $image = imagecreatetruecolor(4, 2);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagesetpixel($image, 0, 0, (int) imagecolorallocate($image, 255, 0, 0));

        return $image;
    }

    private function isRed(\GdImage $image, int $x, int $y): bool
    {
        $color = imagecolorsforindex($image, (int) imagecolorat($image, $x, $y));

        return $color['red'] === 255 && $color['green'] === 0 && $color['blue'] === 0;
    }

    public function testNoOrientationKeepsTheImageUntouched(): void
    {
        $imageHandler = new Image();
        $image = $imageHandler->applyOrientation($this->createMarkedImage(), 'off', 0);

        $this->assertFalse($imageHandler->imageModified);
        $this->assertTrue($this->isRed($image, 0, 0));
    }

    public function testHorizontalFlipMovesTheMarkerToTheRight(): void
    {
        $imageHandler = new Image();
        $image = $imageHandler->applyOrientation($this->createMarkedImage(), 'flip-horizontal', 0);

        $this->assertTrue($imageHandler->imageModified);
        $this->assertFalse($this->isRed($image, 0, 0));
        $this->assertTrue($this->isRed($image, 3, 0));
    }

    public function testVerticalFlipMovesTheMarkerToTheBottom(): void
    {
        $image = (new Image())->applyOrientation($this->createMarkedImage(), 'flip-vertical', 0);

        $this->assertTrue($this->isRed($image, 0, 1));
    }

    public function testRotationIsAppliedAfterFlip(): void
    {
        $image = (new Image())->applyOrientation($this->createMarkedImage(), 'flip-horizontal', 90);

        $this->assertSame(2, imagesx($image));
        $this->assertSame(4, imagesy($image));
        // flipped marker (3,0) ends up in the top left corner after a 90° counter-clockwise rotation
        $this->assertTrue($this->isRed($image, 0, 0));
    }
}

<?php

namespace Photobooth\Tests\Unit;

use Photobooth\Rembg;
use PHPUnit\Framework\TestCase;

final class RembgTest extends TestCase
{
    private string $backgroundFile = '';

    protected function tearDown(): void
    {
        if ($this->backgroundFile !== '' && is_file($this->backgroundFile)) {
            unlink($this->backgroundFile);
        }
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function colorAt(\GdImage $image, int $x, int $y): array
    {
        return imagecolorsforindex($image, (int) imagecolorat($image, $x, $y));
    }

    /**
     * 4x2 background: blue left column, green everywhere else.
     */
    private function createBackgroundFile(): string
    {
        $background = imagecreatetruecolor(4, 2);
        imagefill($background, 0, 0, (int) imagecolorallocate($background, 0, 255, 0));
        imageline($background, 0, 0, 0, 1, (int) imagecolorallocate($background, 0, 0, 255));
        $this->backgroundFile = (string) tempnam(sys_get_temp_dir(), 'rembg_test_');
        imagepng($background, $this->backgroundFile);

        return $this->backgroundFile;
    }

    /**
     * 4x2 transparent foreground with one opaque red pixel in the bottom right corner.
     */
    private function createForeground(): \GdImage
    {
        $foreground = imagecreatetruecolor(4, 2);
        imagealphablending($foreground, false);
        imagesavealpha($foreground, true);
        imagefill($foreground, 0, 0, (int) imagecolorallocatealpha($foreground, 0, 0, 0, 127));
        imagesetpixel($foreground, 3, 1, (int) imagecolorallocatealpha($foreground, 255, 0, 0, 0));
        imagealphablending($foreground, true);

        return $foreground;
    }

    public function testBackgroundIsPlacedBehindTheForegroundWithoutBeingMirrored(): void
    {
        $result = Rembg::applyBackgroundImage($this->createForeground(), $this->createBackgroundFile(), 'stretch');

        $this->assertSame(['red' => 0, 'green' => 0, 'blue' => 255, 'alpha' => 0], $this->colorAt($result, 0, 0));
        $this->assertSame(['red' => 0, 'green' => 255, 'blue' => 0, 'alpha' => 0], $this->colorAt($result, 2, 0));
        $this->assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $this->colorAt($result, 3, 1));
    }

    public function testScaleFillCoversTheWholeCanvas(): void
    {
        $result = Rembg::applyBackgroundImage($this->createForeground(), $this->createBackgroundFile(), 'scale-fill');

        $this->assertSame(4, imagesx($result));
        $this->assertSame(2, imagesy($result));
        for ($x = 0; $x < 3; $x++) {
            $this->assertNotSame(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 0], $this->colorAt($result, $x, 0));
        }
    }

    public function testMissingBackgroundThrows(): void
    {
        $this->expectException(\Exception::class);
        @Rembg::applyBackgroundImage($this->createForeground(), sys_get_temp_dir() . '/rembg_missing_background.png', 'stretch');
    }

    public function testSelectableBackgroundsAreImagesInsideTheBackgroundFolders(): void
    {
        $backgrounds = Rembg::getSelectableBackgrounds();

        $this->assertNotEmpty($backgrounds, 'the example backgrounds are always available');
        foreach ($backgrounds as $background) {
            $folder = dirname($background);
            $this->assertContains($folder, Rembg::SELECTABLE_BACKGROUND_FOLDERS);
            $this->assertTrue(Rembg::isSelectableBackground($background));
        }
    }

    public function testArbitraryPathsAreNotSelectable(): void
    {
        $this->assertFalse(Rembg::isSelectableBackground('config/my.config.inc.php'));
        $this->assertFalse(Rembg::isSelectableBackground('resources/img/background/../../../config/my.config.inc.php'));
        $this->assertFalse(Rembg::isSelectableBackground(Rembg::BACKGROUND_ORIGINAL));
    }

    public function testCutoutFilesAreNamedAfterThePhoto(): void
    {
        $this->assertStringEndsWith('cutout' . DIRECTORY_SEPARATOR . '20261007_101010.png', Rembg::getCutoutFile('20261007_101010.jpg'));
        $this->assertStringEndsWith('cutout' . DIRECTORY_SEPARATOR . '20261007_101010.jpg', Rembg::getOriginalPreviewFile('20261007_101010.jpg'));
    }
}

<?php

/**
 * Stage to choose a background after taking a photo (rembg background selection).
 *
 * @var array $config
 */

use Photobooth\Rembg;
use Photobooth\Service\LanguageService;
use Photobooth\Utility\ComponentUtility;
use Photobooth\Utility\PathUtility;

$languageService = LanguageService::getInstance();

echo '<div class="stage stage--background rotarygroup" data-stage="background">';
echo '<div class="stage-inner">';
echo '<div class="background-select__title">' . htmlspecialchars($languageService->translate('chooseBackground')) . '</div>';
echo '<div class="background-select__preview"><canvas id="backgroundSelectCanvas"></canvas></div>';

echo '<div class="background-select__list">';
if ($config['rembg']['select_background_original']) {
    echo '<button type="button" class="background-select__option rotaryfocus" data-background="' . Rembg::BACKGROUND_ORIGINAL . '">';
    echo '<img class="background-select__thumb" alt="">';
    echo '<span class="background-select__label">' . htmlspecialchars($languageService->translate('backgroundOriginal')) . '</span>';
    echo '</button>';
}
foreach (Rembg::getSelectableBackgrounds() as $background) {
    echo '<button type="button" class="background-select__option rotaryfocus" data-background="' . htmlspecialchars($background) . '">';
    echo '<img class="background-select__thumb" src="' . htmlspecialchars(PathUtility::getPublicPath($background)) . '" alt="" loading="lazy">';
    echo '</button>';
}
echo '</div>';

echo '<div class="buttonbar background-select__buttons">';
echo ComponentUtility::renderButton('confirmBackground', $config['icons']['save'], 'background-confirm');
echo ComponentUtility::renderButton('abort', $config['icons']['close'], 'background-cancel');
echo '</div>';

echo '</div>';
echo '</div>';

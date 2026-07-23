<?php

$source = __DIR__ . '/../public/logo.jpg';
$target = __DIR__ . '/../public/icons';

if (! is_dir($target)) {
    mkdir($target, 0755, true);
}

$logo = imagecreatefromjpeg($source);
$width = imagesx($logo);
$height = imagesy($logo);
$side = min($width, $height);
$sourceX = (int) (($width - $side) / 2);
$sourceY = (int) (($height - $side) / 2);

foreach ([192, 512] as $size) {
    $canvas = imagecreatetruecolor($size, $size);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagecopyresampled($canvas, $logo, 0, 0, $sourceX, $sourceY, $size, $size, $side, $side);
    imagepng($canvas, "{$target}/icon-{$size}.png", 9);
    imagedestroy($canvas);
}

$maskableSize = 512;
$innerSize = 410;
$offset = (int) (($maskableSize - $innerSize) / 2);
$maskable = imagecreatetruecolor($maskableSize, $maskableSize);
imagesavealpha($maskable, true);
imagefill($maskable, 0, 0, imagecolorallocatealpha($maskable, 0, 0, 0, 127));
imagecopyresampled($maskable, $logo, $offset, $offset, $sourceX, $sourceY, $innerSize, $innerSize, $side, $side);
imagepng($maskable, "{$target}/maskable-512.png", 9);
imagedestroy($maskable);
imagedestroy($logo);

<?php
/**
 * N3XT WEB - Captcha Generator
 * 
 * Generates captcha images for security verification.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once dirname(__DIR__) . '/includes/functions.php';

// Generate captcha code
$code = Captcha::generate();

// Create image
$image = Captcha::createImage($code);

// Set content type
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output image
imagepng($image);
imagedestroy($image);
?>
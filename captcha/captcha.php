<?php
session_start();

// Generate a 5-char alphanumeric code (no 0, O, 1, I to avoid confusion)
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code = '';
for ($i = 0; $i < 5; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}
$_SESSION['captcha_code'] = $code;

// Canvas size
$width  = 160;
$height = 52;

$image = imagecreatetruecolor($width, $height);
imagesavealpha($image, true);

// ── Palette ──────────────────────────────────────────────────────
$bg       = imagecolorallocate($image, 245, 246, 249);   // --surface-2
$ink      = imagecolorallocate($image, 15,  17,  23);    // --ink (dark)
$accent   = imagecolorallocate($image, 42,  82,  232);   // --accent blue
$mid      = imagecolorallocate($image, 123, 128, 148);   // --ink-soft
$noise_c  = imagecolorallocate($image, 180, 185, 200);   // light noise

// ── Background fill ───────────────────────────────────────────────
imagefilledrectangle($image, 0, 0, $width, $height, $bg);

// ── Background dot noise ──────────────────────────────────────────
for ($i = 0; $i < 120; $i++) {
    imagesetpixel($image, random_int(0, $width), random_int(0, $height), $noise_c);
}

// ── Wavy distraction lines ─────────────────────────────────────────
for ($i = 0; $i < 4; $i++) {
    $lc = ($i % 2 === 0) ? $noise_c : $mid;
    $y1 = random_int(5, $height - 5);
    $y2 = random_int(5, $height - 5);
    $y3 = random_int(5, $height - 5);
    // Draw a rough bezier-like curve using short line segments
    $steps = 20;
    $prevX = 0;
    $prevY = $y1;
    for ($s = 1; $s <= $steps; $s++) {
        $t    = $s / $steps;
        $curX = (int)($t * $width);
        // Quadratic bezier approximation
        $curY = (int)((1 - $t) * (1 - $t) * $y1 + 2 * (1 - $t) * $t * $y2 + $t * $t * $y3);
        imageline($image, $prevX, $prevY, $curX, $curY, $lc);
        $prevX = $curX;
        $prevY = $curY;
    }
}

// ── Draw each character with slight rotation/offset ────────────────
// Using GD built-in font 5 (bold, largest built-in)
$char_w    = 14;   // approx width per char with font 5
$total_w   = strlen($code) * ($char_w + 4);
$start_x   = (int)(($width - $total_w) / 2);

$colors = [$ink, $accent, $mid, $ink, $accent];

for ($i = 0; $i < strlen($code); $i++) {
    $cx = $start_x + $i * ($char_w + 4);
    $cy = random_int(10, $height - 22);  // vertical jitter
    // Alternate colors for visual variety
    imagestring($image, 5, $cx, $cy, $code[$i], $colors[$i % count($colors)]);
}

// ── Thin border ───────────────────────────────────────────────────
$border = imagecolorallocate($image, 200, 202, 210);
imagerectangle($image, 0, 0, $width - 1, $height - 1, $border);

// ── Output ────────────────────────────────────────────────────────
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($image);
imagedestroy($image);
?>
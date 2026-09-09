<?php

declare(strict_types=1);

/**
 * Sinh toàn bộ bộ icon + splash cho app Stories, vẽ 100% bằng PHP GD.
 *
 * Chạy lại khi muốn đổi thiết kế:
 *   php assets/icon-source/generate-icons.php
 *
 * Ý tưởng: quyển sách mở màu trắng trên nền cam gradient, phía sau là đường chân trời
 * thành phố mờ (gợi mô-típ truyện đô thị/chủ tịch). Vẽ ở độ phân giải x3 rồi thu nhỏ
 * để bờ mượt.
 *
 * Xuất ra assets/images/:
 *   icon.png                     1024, ĐẶC (iOS không cho phép alpha)
 *   android-icon-background.png  1024, nền cam đặc
 *   android-icon-foreground.png  1024, sách trắng trong vùng an toàn 66%, nền trong suốt
 *   android-icon-monochrome.png  1024, bóng sách trắng, nền trong suốt (Android 13 themed)
 *   splash-icon.png               512, icon bo góc, nền trong suốt (splash nền kem)
 *   favicon.png                    96
 */

const SS = 3;

// Bảng màu — khớp accent của app
const ORANGE_LIGHT = [0xFF, 0xA7, 0x6B];
const ORANGE_DEEP  = [0xF2, 0x70, 0x3A];
const WHITE        = [0xFF, 0xFF, 0xFF];

function clamp01(float $v): float { return $v < 0 ? 0.0 : ($v > 1 ? 1.0 : $v); }

function smoothstep(float $a, float $b, float $x): float
{
    if ($a === $b) return $x < $a ? 0.0 : 1.0;
    $t = clamp01(($x - $a) / ($b - $a));
    return $t * $t * (3 - 2 * $t);
}

function lerpC(array $a, array $b, float $t): array
{
    return [$a[0] + ($b[0]-$a[0])*$t, $a[1] + ($b[1]-$a[1])*$t, $a[2] + ($b[2]-$a[2])*$t];
}

/** Trộn 1 pixel màu $c với độ đục $al lên ảnh RGBA. */
function px($img, int $x, int $y, array $c, float $al): void
{
    $w = imagesx($img); $h = imagesy($img);
    if ($al <= 0.002 || $x < 0 || $y < 0 || $x >= $w || $y >= $h) return;
    if ($al > 1) $al = 1.0;

    $d  = imagecolorat($img, $x, $y);
    $da = ($d >> 24) & 0x7F;               // 0 đục .. 127 trong
    $dr = ($d >> 16) & 0xFF; $dg = ($d >> 8) & 0xFF; $db = $d & 0xFF;

    $srcA = $al;
    $dstA = 1.0 - $da / 127.0;
    $outA = $srcA + $dstA * (1 - $srcA);
    if ($outA <= 0.0001) return;

    $r = ($c[0]*$srcA + $dr*$dstA*(1-$srcA)) / $outA;
    $g = ($c[1]*$srcA + $dg*$dstA*(1-$srcA)) / $outA;
    $b = ($c[2]*$srcA + $db*$dstA*(1-$srcA)) / $outA;

    $a127 = (int) round((1 - $outA) * 127);
    imagesetpixel($img, $x, $y, ($a127 << 24) | ((int)$r << 16) | ((int)$g << 8) | (int)$b);
}

function newCanvas(int $size)
{
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefilledrectangle($img, 0, 0, $size-1, $size-1, imagecolorallocatealpha($img, 0, 0, 0, 127));
    return $img;
}

/** Nền cam gradient chéo + quầng sáng góc trên trái. */
function paintBackground($img, int $S): void
{
    for ($y = 0; $y < $S; $y++) {
        for ($x = 0; $x < $S; $x++) {
            $t = clamp01((($x / $S) * 0.45 + ($y / $S) * 0.55));
            $c = lerpC(ORANGE_LIGHT, ORANGE_DEEP, $t);
            // quầng sáng mềm phía trên trái
            $dx = ($x - $S*0.28) / ($S*0.75);
            $dy = ($y - $S*0.22) / ($S*0.75);
            $glow = 1.0 - clamp01(sqrt($dx*$dx + $dy*$dy));
            $c = lerpC($c, [255,255,255], $glow * 0.20);
            px($img, $x, $y, $c, 1.0);
        }
    }
}

/** Đa giác tô mềm: lấy mẫu 1 pixel bằng phép kiểm tra điểm trong đa giác. */
function fillPoly($img, array $pts, array $color, float $alpha = 1.0): void
{
    $xs = array_column($pts, 0); $ys = array_column($pts, 1);
    $x0 = (int) max(0, floor(min($xs))); $x1 = (int) min(imagesx($img)-1, ceil(max($xs)));
    $y0 = (int) max(0, floor(min($ys))); $y1 = (int) min(imagesy($img)-1, ceil(max($ys)));
    $n = count($pts);

    for ($y = $y0; $y <= $y1; $y++) {
        for ($x = $x0; $x <= $x1; $x++) {
            $inside = false;
            $px = $x + 0.5; $py = $y + 0.5;
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                [$xi, $yi] = $pts[$i]; [$xj, $yj] = $pts[$j];
                if ((($yi > $py) !== ($yj > $py)) &&
                    ($px < ($xj - $xi) * ($py - $yi) / (($yj - $yi) ?: 1e-9) + $xi)) {
                    $inside = !$inside;
                }
            }
            if ($inside) px($img, $x, $y, $color, $alpha);
        }
    }
}

/** Hình chữ nhật bo góc. */
function fillRoundRect($img, float $x0, float $y0, float $x1, float $y1, float $r, array $color, float $alpha = 1.0): void
{
    for ($y = (int)floor($y0); $y <= (int)ceil($y1); $y++) {
        for ($x = (int)floor($x0); $x <= (int)ceil($x1); $x++) {
            $cx = min(max($x + 0.5, $x0 + $r), $x1 - $r);
            $cy = min(max($y + 0.5, $y0 + $r), $y1 - $r);
            $d  = sqrt((($x+0.5)-$cx)**2 + (($y+0.5)-$cy)**2);
            $f  = 1.0 - smoothstep($r - 1.2, $r + 0.2, $d);
            if ($f > 0) px($img, $x, $y, $color, $alpha * $f);
        }
    }
}

/**
 * Vẽ đường chân trời thành phố mờ phía sau sách.
 * $baseY = chân toà nhà, các toà cao thấp ngẫu nhiên TẤT ĐỊNH.
 */
function paintSkyline($img, int $S, float $baseY, array $color, float $alpha): void
{
    // Dải thành phố mờ sát đáy: gợi mô-típ đô thị mà không tranh chấp với quyển sách.
    // Các toà nhà DÍNH LIỀN nhau (không có khe) để đọc ra đường chân trời thành phố,
    // chứ không thành biểu đồ cột. Bề rộng và chiều cao biến thiên mạnh.
    mt_srand(20260909);
    $x = -$S * 0.04;
    $prevH = $S * 0.06;
    while ($x < $S * 1.04) {
        $w = $S * (0.028 + mt_rand(0, 100) / 100 * 0.055);
        // chiều cao đi bộ ngẫu nhiên quanh chiều cao trước đó -> đường chân trời liền mạch
        $delta = ($S * 0.055) * (mt_rand(-100, 100) / 100);
        $h = max($S * 0.030, min($S * 0.150, $prevH + $delta));
        fillRoundRect($img, $x, $baseY - $h, $x + $w + $S * 0.002, $baseY, $S * 0.002, $color, $alpha);
        // thỉnh thoảng thêm chóp/ăng-ten cho toà cao
        if ($h > $S * 0.105 && mt_rand(0, 100) > 55) {
            $sw = $w * 0.16;
            fillRoundRect($img, $x + $w/2 - $sw/2, $baseY - $h - $S * 0.030, $x + $w/2 + $sw/2, $baseY - $h, $sw/2, $color, $alpha);
        }
        $prevH = $h;
        $x += $w;
    }
}


/** Bóng mềm hắt xuống dưới quyển sách. */
function paintBookShadow($img, int $S): void
{
    $cy = $S * 0.760; $rx = $S * 0.330; $ry = $S * 0.045;
    $x0 = (int)($cy ? $S*0.14 : 0); $x1 = (int)($S*0.86);
    for ($y = (int)($cy - $ry*2.2); $y <= (int)($cy + $ry*2.2); $y++) {
        for ($x = $x0; $x <= $x1; $x++) {
            $u = (($x+0.5) - $S*0.5) / $rx;
            $v = (($y+0.5) - $cy) / $ry;
            $q = sqrt($u*$u + $v*$v);
            if ($q >= 1.6) continue;
            $f = 1.0 - smoothstep(0.0, 1.6, $q);
            px($img, $x, $y, [0x8A, 0x33, 0x10], 0.22 * $f * $f);
        }
    }
}

/** Quyển sách mở: 2 trang hình thang + gáy sách. */
function paintBook($img, int $S, array $color, float $alpha = 1.0): void
{
    $cx = $S * 0.5;
    $topOuter = $S * 0.275;   // mép ngoài trên
    $topInner = $S * 0.345;   // mép trong (gáy) trên — thấp hơn tạo dáng sách mở
    $botOuter = $S * 0.660;
    $botInner = $S * 0.730;
    $left     = $S * 0.150;
    $right    = $S * 0.850;
    $gap      = $S * 0.013;   // khe gáy

    // Trang trái
    fillPoly($img, [
        [$left, $topOuter], [$cx - $gap, $topInner],
        [$cx - $gap, $botInner], [$left, $botOuter],
    ], $color, $alpha);

    // Trang phải
    fillPoly($img, [
        [$cx + $gap, $topInner], [$right, $topOuter],
        [$right, $botOuter], [$cx + $gap, $botInner],
    ], $color, $alpha);
}

/** Các dòng chữ gợi ý trên trang sách (màu cam, nằm trên nền trắng của sách). */
function paintTextLines($img, int $S, array $color, float $alpha): void
{
    $cx = $S * 0.5; $gap = $S * 0.016;
    $rows = [0.415, 0.480, 0.545, 0.610];
    foreach ($rows as $i => $ry) {
        $shrink = $i * $S * 0.016;
        $y = $S * $ry;
        $h = $S * 0.020;
        // trái
        fillRoundRect($img, $S*0.215 + $shrink, $y, $cx - $gap - $S*0.045, $y + $h, $h/2, $color, $alpha);
        // phải
        fillRoundRect($img, $cx + $gap + $S*0.045, $y, $S*0.785 - $shrink, $y + $h, $h/2, $color, $alpha);
    }
}

/**
 * Dựng logo (sách + skyline) lên canvas kích thước $S.
 * $scale co nhỏ hình để lọt vùng an toàn của adaptive icon.
 */
function drawMark($img, int $S, array $bookColor, ?array $skylineColor, ?array $lineColor, float $scale = 1.0)
{
    if ($scale === 1.0) {
        if ($skylineColor) paintSkyline($img, $S, $S * 0.960, $skylineColor, 0.30);
        if ($skylineColor) paintBookShadow($img, $S);
        paintBook($img, $S, $bookColor);
        if ($lineColor) paintTextLines($img, $S, $lineColor, 0.85);
        return $img;
    }

    // Vẽ full size rồi thu nhỏ vào giữa
    $tmp = newCanvas($S);
    if ($skylineColor) paintSkyline($tmp, $S, $S * 0.960, $skylineColor, 0.30);
    paintBook($tmp, $S, $bookColor);
    if ($lineColor) paintTextLines($tmp, $S, $lineColor, 0.85);

    $inner = (int) round($S * $scale);
    $off   = (int) round(($S - $inner) / 2);
    imagealphablending($img, false);
    imagecopyresampled($img, $tmp, $off, $off, 0, 0, $inner, $inner, $S, $S);
    imagedestroy($tmp);
    return $img;
}

/** Thu nhỏ về kích thước đích, giữ alpha. */
function downscale($src, int $target)
{
    $out = imagecreatetruecolor($target, $target);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefilledrectangle($out, 0, 0, $target-1, $target-1, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $src, 0, 0, 0, 0, $target, $target, imagesx($src), imagesy($src));
    return $out;
}

function save($img, string $path): void
{
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagepng($img, $path, 6);
    echo '  ✓ '.basename($path).'  '.imagesx($img).'x'.imagesy($img).'  '.round(filesize($path)/1024).' KB'."\n";
}

// ---------------------------------------------------------------- xuất file

$out = __DIR__.'/../images';
$S   = 1024 * SS;

echo "Sinh icon cho Stories...\n";

// 1) icon.png — ĐẶC (iOS cấm alpha): nền cam + skyline + sách trắng
$icon = newCanvas($S);
paintBackground($icon, $S);
paintSkyline($icon, $S, $S * 0.960, [0xC9, 0x53, 0x22], 0.20);
paintBook($icon, $S, WHITE);
paintTextLines($icon, $S, ORANGE_DEEP, 0.80);
save(downscale($icon, 1024), "$out/icon.png");

// 2) android-icon-background.png — nền cam đặc
$bg = newCanvas($S);
paintBackground($bg, $S);
save(downscale($bg, 1024), "$out/android-icon-background.png");

// 3) android-icon-foreground.png — sách trắng, thu vào vùng an toàn 66%
$fg = newCanvas($S);
drawMark($fg, $S, WHITE, [0xC9, 0x53, 0x22], ORANGE_DEEP, 0.66);
save(downscale($fg, 1024), "$out/android-icon-foreground.png");

// 4) android-icon-monochrome.png — chỉ bóng sách trắng
$mono = newCanvas($S);
drawMark($mono, $S, WHITE, null, null, 0.66);
save(downscale($mono, 1024), "$out/android-icon-monochrome.png");

// 5) splash-icon.png — icon bo góc trên nền trong suốt (splash nền kem)
$sp   = newCanvas($S);
$card = newCanvas($S);
paintBackground($card, $S);
paintSkyline($card, $S, $S * 0.960, [0xC9, 0x53, 0x22], 0.20);
paintBook($card, $S, WHITE);
paintTextLines($card, $S, ORANGE_DEEP, 0.80);
// mặt nạ bo góc
$mask = newCanvas($S);
fillRoundRect($mask, 0, 0, $S-1, $S-1, $S * 0.235, [255,255,255], 1.0);
for ($y = 0; $y < $S; $y++) {
    for ($x = 0; $x < $S; $x++) {
        $ma = (imagecolorat($mask, $x, $y) >> 24) & 0x7F;
        if ($ma >= 127) continue;
        $c = imagecolorat($card, $x, $y);
        px($sp, $x, $y, [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF], 1.0 - $ma / 127.0);
    }
}
imagedestroy($mask); imagedestroy($card);
save(downscale($sp, 512), "$out/splash-icon.png");

// 6) favicon.png
save(downscale($icon, 96), "$out/favicon.png");

echo "Xong.\n";

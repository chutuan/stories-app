<?php

declare(strict_types=1);

/**
 * Sinh ARTWORK bìa truyện cho seed data — vẽ 100% bằng PHP GD, KHÔNG CÓ BẤT KỲ CHỮ NÀO.
 *
 * Tên truyện / tác giả do app tự hiển thị đè lên, nên ảnh chỉ là artwork thuần.
 *
 * Chạy lại khi muốn đổi thiết kế bìa:
 *   php database/seeders/covers/generate-covers.php
 *
 * Đặc điểm kỹ thuật:
 *  - Vẽ ở 1200x1600 (siêu lấy mẫu x2) rồi thu nhỏ về 600x800 -> khử răng cưa toàn ảnh.
 *  - Gradient nhiều điểm dừng + nội suy smoothstep.
 *  - Mọi hình mềm (quầng sáng, bokeh, sương, cánh hoa, vệt kiếm) đều tự trộn alpha theo pixel
 *    nên bờ mượt, không bị viền cứng.
 *  - Ngẫu nhiên TẤT ĐỊNH: mt_srand(crc32($slug)) -> chạy lại luôn ra ảnh y hệt.
 *  - Xuất JPEG 600x800, chất lượng 90, tên file theo slug (StorySeeder copy theo slug).
 */

if (! extension_loaded('gd')) {
    fwrite(STDERR, "Thiếu extension GD.\n");
    exit(1);
}

const OUT_W = 600;
const OUT_H = 800;
const SS = 2;                 // hệ số siêu lấy mẫu
const W = OUT_W * SS;
const H = OUT_H * SS;

/* ────────────────────────────── Tiện ích màu / pixel ────────────────────────────── */

/** Đọc màu hex '#RRGGBB' thành [r,g,b]. */
function hex(string $h): array
{
    $h = ltrim($h, '#');

    return [
        (int) hexdec(substr($h, 0, 2)),
        (int) hexdec(substr($h, 2, 2)),
        (int) hexdec(substr($h, 4, 2)),
    ];
}

function lerp(float $a, float $b, float $t): float
{
    return $a + ($b - $a) * $t;
}

function lerpColor(array $a, array $b, float $t): array
{
    return [lerp($a[0], $b[0], $t), lerp($a[1], $b[1], $t), lerp($a[2], $b[2], $t)];
}

function clamp01(float $v): float
{
    return $v < 0 ? 0.0 : ($v > 1 ? 1.0 : $v);
}

/** Chuyển tiếp mượt (Hermite) giữa hai mốc. */
function smoothstep(float $edge0, float $edge1, float $x): float
{
    if ($edge0 === $edge1) {
        return $x < $edge0 ? 0.0 : 1.0;
    }
    $t = clamp01(($x - $edge0) / ($edge1 - $edge0));

    return $t * $t * (3 - 2 * $t);
}

/** Trộn một pixel màu $c với độ đục $a (0..1) lên ảnh siêu lấy mẫu. */
function px($img, int $x, int $y, array $c, float $a): void
{
    if ($a <= 0.002 || $x < 0 || $y < 0 || $x >= W || $y >= H) {
        return;
    }
    if ($a > 1) {
        $a = 1.0;
    }
    $d = imagecolorat($img, $x, $y);
    $dr = ($d >> 16) & 0xFF;
    $dg = ($d >> 8) & 0xFF;
    $db = $d & 0xFF;

    imagesetpixel($img, $x, $y,
        ((int) ($dr + ($c[0] - $dr) * $a) << 16) |
        ((int) ($dg + ($c[1] - $dg) * $a) << 8) |
        (int) ($db + ($c[2] - $db) * $a)
    );
}

/** Lấy màu tại vị trí $t trong danh sách điểm dừng [[pos, [r,g,b]], ...]. */
function sampleStops(array $stops, float $t): array
{
    $n = count($stops);
    if ($t <= $stops[0][0]) {
        return $stops[0][1];
    }
    if ($t >= $stops[$n - 1][0]) {
        return $stops[$n - 1][1];
    }
    for ($i = 0; $i < $n - 1; $i++) {
        [$p0, $c0] = $stops[$i];
        [$p1, $c1] = $stops[$i + 1];
        if ($t >= $p0 && $t <= $p1) {
            $f = $p1 > $p0 ? ($t - $p0) / ($p1 - $p0) : 0.0;
            $f = $f * $f * (3 - 2 * $f);

            return lerpColor($c0, $c1, $f);
        }
    }

    return $stops[$n - 1][1];
}

/** Nền gradient dọc nhiều điểm dừng. */
function gradientFill($img, array $stops): void
{
    for ($y = 0; $y < H; $y++) {
        [$r, $g, $b] = sampleStops($stops, $y / (H - 1));
        imageline($img, 0, $y, W - 1, $y, ((int) $r << 16) | ((int) $g << 8) | (int) $b);
    }
}

/* ────────────────────────────── Hình mềm (tự trộn alpha) ────────────────────────────── */

/**
 * Đĩa mềm: đục ở lõi rồi tắt dần ra rìa.
 * $edge = 0 -> đĩa đặc (chỉ mềm 1px ở rìa); $edge = 1 -> quầng sáng tắt dần từ tâm.
 */
function softDisc($img, float $cx, float $cy, float $r, array $color, float $alpha, float $edge = 0.02, float $power = 1.6): void
{
    $x0 = (int) max(0, floor($cx - $r));
    $x1 = (int) min(W - 1, ceil($cx + $r));
    $y0 = (int) max(0, floor($cy - $r));
    $y1 = (int) min(H - 1, ceil($cy + $r));
    $inner = $r * (1 - max($edge, 1.2 / max($r, 1)));
    $r2 = $r * $r;

    for ($y = $y0; $y <= $y1; $y++) {
        $dy = $y - $cy;
        $dy2 = $dy * $dy;
        for ($x = $x0; $x <= $x1; $x++) {
            $dx = $x - $cx;
            $d2 = $dx * $dx + $dy2;
            if ($d2 > $r2) {
                continue;
            }
            $d = sqrt($d2);
            $f = $d <= $inner ? 1.0 : 1.0 - smoothstep($inner, $r, $d);
            if ($power !== 1.0) {
                $f = pow($f, $power);
            }
            px($img, $x, $y, $color, $alpha * $f);
        }
    }
}

/** Vòng tròn mềm (viền dày $thickness, tắt dần hai bên). */
function softRing($img, float $cx, float $cy, float $radius, float $thickness, array $color, float $alpha): void
{
    $outer = $radius + $thickness;
    $x0 = (int) max(0, floor($cx - $outer));
    $x1 = (int) min(W - 1, ceil($cx + $outer));
    $y0 = (int) max(0, floor($cy - $outer));
    $y1 = (int) min(H - 1, ceil($cy + $outer));

    for ($y = $y0; $y <= $y1; $y++) {
        $dy = $y - $cy;
        $dy2 = $dy * $dy;
        for ($x = $x0; $x <= $x1; $x++) {
            $dx = $x - $cx;
            $d = sqrt($dx * $dx + $dy2);
            $f = 1.0 - smoothstep(0.0, $thickness, abs($d - $radius));
            if ($f <= 0) {
                continue;
            }
            px($img, $x, $y, $color, $alpha * $f * $f);
        }
    }
}

/** Ellipse mềm có xoay — dùng làm cánh hoa. */
function softPetal($img, float $cx, float $cy, float $rx, float $ry, float $angle, array $color, float $alpha): void
{
    $rad = max($rx, $ry) + 2;
    $x0 = (int) max(0, floor($cx - $rad));
    $x1 = (int) min(W - 1, ceil($cx + $rad));
    $y0 = (int) max(0, floor($cy - $rad));
    $y1 = (int) min(H - 1, ceil($cy + $rad));
    $cos = cos(-$angle);
    $sin = sin(-$angle);
    $feather = 1.6 * SS;

    for ($y = $y0; $y <= $y1; $y++) {
        for ($x = $x0; $x <= $x1; $x++) {
            $dx = $x - $cx;
            $dy = $y - $cy;
            $u = $dx * $cos - $dy * $sin;
            $v = $dx * $sin + $dy * $cos;
            $q = sqrt(($u / $rx) * ($u / $rx) + ($v / $ry) * ($v / $ry));
            if ($q >= 1.15) {
                continue;
            }
            // đầu cánh hơi nhọn: kéo nhẹ theo trục dài
            $f = 1.0 - smoothstep(1.0 - $feather / max($rx, $ry), 1.0, $q);
            px($img, $x, $y, $color, $alpha * $f);
        }
    }
}

/**
 * Đoạn thẳng phát sáng: lõi dày $core, tắt dần thêm $feather, mờ dần về hai đầu.
 */
function softSegment($img, float $x1, float $y1, float $x2, float $y2, float $core, float $feather, array $color, float $alpha, bool $taper = false): void
{
    $pad = $core + $feather + 1;
    $bx0 = (int) max(0, floor(min($x1, $x2) - $pad));
    $bx1 = (int) min(W - 1, ceil(max($x1, $x2) + $pad));
    $by0 = (int) max(0, floor(min($y1, $y2) - $pad));
    $by1 = (int) min(H - 1, ceil(max($y1, $y2) + $pad));

    $vx = $x2 - $x1;
    $vy = $y2 - $y1;
    $len2 = $vx * $vx + $vy * $vy;
    if ($len2 <= 0.0001) {
        return;
    }

    for ($y = $by0; $y <= $by1; $y++) {
        for ($x = $bx0; $x <= $bx1; $x++) {
            $wx = $x - $x1;
            $wy = $y - $y1;
            $s = ($wx * $vx + $wy * $vy) / $len2;
            $sc = $s < 0 ? 0.0 : ($s > 1 ? 1.0 : $s);
            $px1 = $x1 + $vx * $sc;
            $py1 = $y1 + $vy * $sc;
            $d = sqrt(($x - $px1) * ($x - $px1) + ($y - $py1) * ($y - $py1));
            $f = 1.0 - smoothstep($core, $core + $feather, $d);
            if ($f <= 0) {
                continue;
            }
            if ($taper) {
                $f *= pow(sin(M_PI * $sc), 0.55);
            }
            px($img, $x, $y, $color, $alpha * $f);
        }
    }
}

/** Dải sương ngang uốn lượn (tổng các sóng sin tất định). */
function fogBand($img, float $baseY, float $thickness, float $wobble, array $color, float $alpha): void
{
    $p1 = mt_rand(0, 628) / 100;
    $p2 = mt_rand(0, 628) / 100;
    $p3 = mt_rand(0, 628) / 100;
    $reach = $thickness * 2.6;
    $y0 = (int) max(0, floor($baseY - $reach - $wobble));
    $y1 = (int) min(H - 1, ceil($baseY + $reach + $wobble));

    for ($x = 0; $x < W; $x++) {
        $t = $x / (W - 1);
        $yc = $baseY
            + $wobble * 0.6 * sin($t * 3.1 + $p1)
            + $wobble * 0.3 * sin($t * 7.7 + $p2)
            + $wobble * 0.15 * sin($t * 15.3 + $p3);
        $amp = 0.72 + 0.28 * sin($t * 5.2 + $p3);
        for ($y = $y0; $y <= $y1; $y++) {
            $u = ($y - $yc) / $thickness;
            $f = exp(-$u * $u);
            if ($f < 0.01) {
                continue;
            }
            px($img, $x, $y, $color, $alpha * $f * $amp);
        }
    }
}

/* ────────────────────────────── Nhiễu 1D cho đường chân núi ────────────────────────────── */

function noiseSeries(int $n): array
{
    $a = [];
    for ($i = 0; $i < $n; $i++) {
        $a[] = mt_rand(0, 10000) / 10000;
    }

    return $a;
}

function noiseAt(array $a, float $t, bool $smooth = true): float
{
    $n = count($a);
    $x = clamp01($t) * ($n - 1);
    $i = (int) floor($x);
    $f = $x - $i;
    $i0 = max(0, min($n - 1, $i));
    $i1 = max(0, min($n - 1, $i + 1));
    if ($smooth) {
        $f = $f * $f * (3 - 2 * $f);
    }

    return $a[$i0] + ($a[$i1] - $a[$i0]) * $f;
}

/**
 * Tạo hàm chiều cao của một rặng núi.
 * $ridged = true -> tạo nếp gấp nhọn (kiếm hiệp); $smooth = false -> đỉnh góc cạnh.
 */
function makeRidge(float $baseY, float $amp, int $pts, bool $ridged = false, bool $smooth = true, float $sharp = 1.0): callable
{
    $a = noiseSeries($pts);
    $b = noiseSeries($pts * 3);

    return function (int $x) use ($a, $b, $baseY, $amp, $ridged, $smooth, $sharp) {
        $t = $x / (W - 1);
        $v = 0.74 * noiseAt($a, $t, $smooth) + 0.26 * noiseAt($b, $t, $smooth);
        if ($ridged) {
            $v = 1.0 - abs(2 * $v - 1);
        }
        if ($sharp !== 1.0) {
            $v = pow(clamp01($v), $sharp);
        }

        return $baseY - $amp * $v;
    };
}

/** Tô khối núi/tòa nhà từ đường chân trên xuống đáy ảnh, có gradient trong thân + AA mép trên. */
function fillTerrain($img, callable $heightFn, array $cTop, array $cBot, float $alpha): void
{
    for ($x = 0; $x < W; $x++) {
        $yTop = $heightFn($x);
        if ($yTop >= H) {
            continue;
        }
        $y0 = (int) floor($yTop);
        $cov = 1.0 - ($yTop - $y0);
        $span = max(1.0, H - $yTop);

        if ($y0 >= 0) {
            px($img, $x, $y0, $cTop, $alpha * $cov);
        }
        for ($y = max(0, $y0 + 1); $y < H; $y++) {
            $t = ($y - $yTop) / $span;
            px($img, $x, $y, lerpColor($cTop, $cBot, $t), $alpha);
        }
    }
}

/* ────────────────────────────── Thành phần cảnh ────────────────────────────── */

/** Sao li ti — dày ở trên, thưa dần xuống dưới; vài ngôi có quầng sáng. */
function starField($img, int $count, float $maxY, array $color, float $baseAlpha = 0.9): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(0, W - 1);
        $y = mt_rand(0, (int) $maxY);
        // thưa dần khi xuống thấp
        $depth = 1.0 - ($y / max($maxY, 1));
        if (mt_rand(0, 100) / 100 > 0.25 + 0.75 * $depth) {
            continue;
        }
        $a = $baseAlpha * (0.25 + 0.75 * (mt_rand(0, 100) / 100)) * (0.35 + 0.65 * $depth);
        $r = (mt_rand(60, 175) / 100) * SS * 0.55;
        softDisc($img, (float) $x, (float) $y, $r, $color, $a, 0.9, 1.2);

        if (mt_rand(0, 100) < 7) {
            softDisc($img, (float) $x, (float) $y, $r * 7, $color, $a * 0.22, 1.0, 2.2);
        }
    }
}

/** Mặt trăng: quầng ngoài + đĩa + gợn miệng núi lửa mờ. */
function drawMoon($img, float $cx, float $cy, float $r, array $core, array $glow, float $glowStrength = 0.5, bool $craters = true): void
{
    softDisc($img, $cx, $cy, $r * 5.2, $glow, $glowStrength * 0.30, 1.0, 2.6);
    softDisc($img, $cx, $cy, $r * 2.4, $glow, $glowStrength * 0.38, 1.0, 2.0);
    softDisc($img, $cx, $cy, $r * 1.35, $glow, $glowStrength * 0.34, 1.0, 1.6);
    softDisc($img, $cx, $cy, $r, $core, 1.0, 0.035, 1.0);

    if ($craters) {
        $shade = lerpColor($core, [10, 10, 30], 0.16);
        for ($i = 0; $i < 7; $i++) {
            $ang = mt_rand(0, 628) / 100;
            $dist = ($r * 0.78) * sqrt(mt_rand(0, 100) / 100);
            $cr = $r * (mt_rand(7, 20) / 100);
            softDisc($img, $cx + cos($ang) * $dist, $cy + sin($ang) * $dist, $cr, $shade, 0.42, 0.85, 1.4);
        }
    }
}

/**
 * Trăng khuyết — vẽ thẳng hình lưỡi liềm (trong đĩa chính NHƯNG ngoài đĩa khoét),
 * không tô đè đĩa màu nền nên không để lại vệt tối lộ ra trên quầng sáng.
 */
function drawCrescent($img, float $cx, float $cy, float $r, array $core, array $glow, float $offset = 0.44): void
{
    softDisc($img, $cx, $cy, $r * 4.6, $glow, 0.20, 1.0, 2.6);
    softDisc($img, $cx, $cy, $r * 1.9, $glow, 0.24, 1.0, 1.9);

    $ox = $cx + $r * $offset;
    $oy = $cy - $r * $offset * 0.55;
    $or = $r * 0.94;

    $x0 = (int) max(0, floor($cx - $r));
    $x1 = (int) min(W - 1, ceil($cx + $r));
    $y0 = (int) max(0, floor($cy - $r));
    $y1 = (int) min(H - 1, ceil($cy + $r));

    for ($y = $y0; $y <= $y1; $y++) {
        for ($x = $x0; $x <= $x1; $x++) {
            $d = sqrt(($x - $cx) ** 2 + ($y - $cy) ** 2);
            $inside = 1.0 - smoothstep($r - 1.2 * SS, $r + 0.4 * SS, $d);
            if ($inside <= 0) {
                continue;
            }
            $d2 = sqrt(($x - $ox) ** 2 + ($y - $oy) ** 2);
            $cut = smoothstep($or - 1.2 * SS, $or + 0.4 * SS, $d2);
            $f = $inside * $cut;
            if ($f <= 0) {
                continue;
            }
            px($img, $x, $y, $core, $f);
        }
    }
}

/** Đường chân trời thành phố: khối nhà cao thấp + ô cửa sổ sáng. */
function skyline($img, float $baseY, float $minH, float $maxH, array $cTop, array $cBot, float $alpha, array $winColor, float $winAlpha, float $winChance, bool $windows = true, int $minW = 26, int $maxW = 62): void
{
    $x = -mt_rand(0, 40) * SS;
    while ($x < W) {
        $bw = mt_rand($minW, $maxW) * SS;
        $bh = ($minH + ($maxH - $minH) * pow(mt_rand(0, 100) / 100, 1.5)) * SS;
        $top = $baseY - $bh;
        $x2 = min(W - 1, $x + $bw);

        // thân nhà, gradient dọc
        for ($cx = max(0, (int) $x); $cx <= $x2; $cx++) {
            $edge = ($cx === max(0, (int) $x) || $cx === (int) $x2) ? 0.86 : 1.0;
            for ($cy = (int) max(0, $top); $cy < min(H, (int) $baseY); $cy++) {
                $t = ($cy - $top) / max(1.0, $baseY - $top);
                px($img, $cx, $cy, lerpColor($cTop, $cBot, $t), $alpha * $edge);
            }
        }

        // chóp: bể nước / cột ăng-ten
        if ($bh > 120 * SS && mt_rand(0, 100) < 45) {
            $aw = max(1, (int) (2 * SS));
            $ax = (int) ($x + $bw * (mt_rand(25, 75) / 100));
            $ah = mt_rand(16, 44) * SS;
            for ($cy = (int) max(0, $top - $ah); $cy < (int) $top; $cy++) {
                for ($cx = $ax; $cx < $ax + $aw; $cx++) {
                    px($img, $cx, $cy, $cTop, $alpha * 0.9);
                }
            }
        }

        if ($windows) {
            $wcell = 7 * SS;
            $wgap = 5 * SS;
            $ww = 3 * SS;
            $wh = 4 * SS;
            for ($wy = (int) ($top + 10 * SS); $wy < $baseY - 8 * SS; $wy += $wcell + $wgap) {
                for ($wx = (int) ($x + 6 * SS); $wx < $x2 - 5 * SS; $wx += $ww + 4 * SS) {
                    if (mt_rand(0, 1000) / 1000 > $winChance) {
                        continue;
                    }
                    $glow = $winAlpha * (0.55 + 0.45 * (mt_rand(0, 100) / 100));
                    for ($gy = $wy; $gy < $wy + $wh; $gy++) {
                        for ($gx = $wx; $gx < $wx + $ww; $gx++) {
                            px($img, $gx, $gy, $winColor, $glow);
                        }
                    }
                    if (mt_rand(0, 100) < 22) {
                        softDisc($img, $wx + $ww / 2, $wy + $wh / 2, 5.0 * SS, $winColor, $glow * 0.28, 1.0, 2.0);
                    }
                }
            }
        }

        $x += $bw + mt_rand(2, 9) * SS;
    }
}

/** Cây khẳng khiu — đệ quy, cành thon dần. */
function branch($img, float $x, float $y, float $angle, float $len, float $thick, int $depth, array $color, float $alpha): void
{
    if ($depth <= 0 || $len < 3 * SS) {
        return;
    }
    $x2 = $x + cos($angle) * $len;
    $y2 = $y + sin($angle) * $len;
    softSegment($img, $x, $y, $x2, $y2, max(0.4, $thick), 1.1 * SS, $color, $alpha);

    $n = mt_rand(2, 3);
    for ($i = 0; $i < $n; $i++) {
        $delta = (mt_rand(18, 62) / 100) * (mt_rand(0, 1) ? 1 : -1);
        branch(
            $img,
            $x2, $y2,
            $angle + $delta,
            $len * (mt_rand(58, 78) / 100),
            $thick * 0.6,
            $depth - 1,
            $color,
            $alpha
        );
    }
}

/** Vệt chém sắc như lưỡi kiếm: quầng rộng -> lõi trắng + tia lửa. */
function swordSlash($img, float $x1, float $y1, float $x2, float $y2, array $glow, array $core, float $strength = 1.0): void
{
    softSegment($img, $x1, $y1, $x2, $y2, 10 * SS, 46 * SS, $glow, 0.17 * $strength, true);
    softSegment($img, $x1, $y1, $x2, $y2, 4 * SS, 16 * SS, $glow, 0.30 * $strength, true);
    softSegment($img, $x1, $y1, $x2, $y2, 1.4 * SS, 4.5 * SS, $core, 0.85 * $strength, true);

    $len = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
    $nx = -($y2 - $y1) / $len;
    $ny = ($x2 - $x1) / $len;
    for ($i = 0; $i < 46; $i++) {
        $s = mt_rand(5, 95) / 100;
        $off = (mt_rand(-70, 70) / 10) * SS;
        $sx = lerp($x1, $x2, $s) + $nx * $off;
        $sy = lerp($y1, $y2, $s) + $ny * $off;
        $sr = (mt_rand(4, 16) / 10) * SS;
        $sa = 0.75 * $strength * pow(sin(M_PI * $s), 0.6) * (mt_rand(30, 100) / 100);
        softDisc($img, $sx, $sy, $sr, $core, $sa, 0.9, 1.3);
        softDisc($img, $sx, $sy, $sr * 5, $glow, $sa * 0.20, 1.0, 2.2);
    }
}

/** Bokeh: vòng tròn mềm, xen kẽ đĩa đặc và vòng rỗng. */
function bokehField($img, int $count, array $palette, float $alphaMax, float $rMin, float $rMax): void
{
    for ($i = 0; $i < $count; $i++) {
        $cx = mt_rand(-40, W + 40);
        $cy = mt_rand(-40, H + 40);
        $r = lerp($rMin, $rMax, pow(mt_rand(0, 100) / 100, 1.7)) * SS;
        $c = $palette[mt_rand(0, count($palette) - 1)];
        $a = $alphaMax * (0.30 + 0.70 * (mt_rand(0, 100) / 100));

        if (mt_rand(0, 100) < 42) {
            softRing($img, (float) $cx, (float) $cy, $r, $r * 0.30, $c, $a * 0.95);
            softDisc($img, (float) $cx, (float) $cy, $r * 0.92, $c, $a * 0.22, 1.0, 1.4);
        } else {
            softDisc($img, (float) $cx, (float) $cy, $r, $c, $a * 0.75, 0.75, 1.5);
        }
    }
}

/* ────────────────────────────── Các motif theo thể loại ────────────────────────────── */

/** Tiên Hiệp / Huyền Huyễn: núi chồng lớp mờ dần + trăng lớn + sao li ti. */
function motifMountain($img, array $cfg): void
{
    gradientFill($img, $cfg['sky']);
    starField($img, $cfg['stars'], H * 0.68, $cfg['starColor']);

    // quầng sáng chân trời (đặt ngay trên đường chân núi để dải màu ấm lộ ra)
    softDisc($img, W * $cfg['glowX'], H * $cfg['glowY'], W * $cfg['glowR'], $cfg['glow'], 0.42, 1.0, 2.1);

    drawMoon($img, W * $cfg['moonX'], H * $cfg['moonY'], W * $cfg['moonR'], $cfg['moonCore'], $cfg['moonGlow'], 0.55);

    // mây mỏng vắt ngang mặt trăng
    foreach ($cfg['clouds'] as [$cy, $th, $wob, $al]) {
        fogBand($img, H * $cy, $th * SS, $wob * SS, $cfg['cloud'], $al);
    }

    $layers = $cfg['layers'];
    $n = count($layers);
    foreach ($layers as $i => [$baseY, $amp, $pts, $colTop, $colBot, $alpha]) {
        $ridge = makeRidge(H * $baseY, H * $amp, $pts, false, true, 1.15);
        fillTerrain($img, $ridge, hex($colTop), hex($colBot), $alpha);

        // sương đọng dưới chân mỗi lớp -> tách lớp, tạo chiều sâu
        if ($i < $n - 1) {
            fogBand($img, H * $baseY - H * $amp * 0.10, 26 * SS, 12 * SS, $cfg['mist'], 0.16 * (1 - $i / $n) + 0.07);
        }
    }
}

/** Kiếm Hiệp: núi nhọn + vầng trăng + đường chéo sắc như lưỡi kiếm. */
function motifSword($img, array $cfg): void
{
    gradientFill($img, $cfg['sky']);
    starField($img, $cfg['stars'], H * 0.6, $cfg['starColor'], 0.75);
    softDisc($img, W * $cfg['glowX'], H * $cfg['glowY'], W * $cfg['glowR'], $cfg['glow'], 0.38, 1.0, 2.2);

    if ($cfg['crescent']) {
        drawCrescent($img, W * $cfg['moonX'], H * $cfg['moonY'], W * $cfg['moonR'], $cfg['moonCore'], $cfg['moonGlow']);
    } else {
        drawMoon($img, W * $cfg['moonX'], H * $cfg['moonY'], W * $cfg['moonR'], $cfg['moonCore'], $cfg['moonGlow'], 0.5, false);
    }

    // núi nhọn, ít điểm điều khiển + nội suy tuyến tính -> đỉnh góc cạnh
    foreach ($cfg['layers'] as $i => [$baseY, $amp, $pts, $colTop, $colBot, $alpha]) {
        $ridge = makeRidge(H * $baseY, H * $amp, $pts, true, false);
        fillTerrain($img, $ridge, hex($colTop), hex($colBot), $alpha);
        if ($i === 0) {
            fogBand($img, H * $baseY - H * $amp * 0.05, 30 * SS, 14 * SS, $cfg['mist'], 0.20);
        }
    }

    foreach ($cfg['slashes'] as [$ax, $ay, $bx, $by, $strength]) {
        swordSlash($img, W * $ax, H * $ay, W * $bx, H * $by, $cfg['slashGlow'], $cfg['slashCore'], $strength);
    }
}

/** Đô Thị / Trọng Sinh: đường chân trời thành phố, khối nhà cao thấp + ô cửa sổ sáng. */
function motifCity($img, array $cfg): void
{
    gradientFill($img, $cfg['sky']);
    starField($img, $cfg['stars'], H * 0.42, $cfg['starColor'], 0.55);

    // vầng sáng ô nhiễm ánh đèn phía chân trời
    softDisc($img, W * $cfg['glowX'], H * $cfg['glowY'], W * $cfg['glowR'], $cfg['glow'], 0.50, 1.0, 2.0);
    softDisc($img, W * (1 - $cfg['glowX']), H * ($cfg['glowY'] + 0.05), W * 0.62, $cfg['glow2'], 0.28, 1.0, 2.2);

    if ($cfg['moon']) {
        drawMoon($img, W * $cfg['moonX'], H * $cfg['moonY'], W * $cfg['moonR'], $cfg['moonCore'], $cfg['moonGlow'], 0.45);
    }

    foreach ($cfg['bands'] as [$baseY, $minH, $maxH, $cTop, $cBot, $alpha, $winA, $winChance, $wins, $minW, $maxW]) {
        skyline(
            $img,
            H * $baseY, $minH, $maxH,
            hex($cTop), hex($cBot), $alpha,
            $cfg['window'], $winA, $winChance, $wins, $minW, $maxW
        );
        fogBand($img, H * $baseY - 6 * SS, 30 * SS, 8 * SS, $cfg['haze'], 0.16);
    }

    // sương đèn phủ đáy
    fogBand($img, H * 0.965, 60 * SS, 6 * SS, $cfg['haze'], 0.14);
}

/** Ngôn Tình: bokeh mềm tông hồng/cam + cánh hoa rơi. */
function motifRomance($img, array $cfg): void
{
    gradientFill($img, $cfg['sky']);

    softDisc($img, W * $cfg['sunX'], H * $cfg['sunY'], W * 0.95, $cfg['sunGlow'], 0.55, 1.0, 2.0);
    softDisc($img, W * $cfg['sunX'], H * $cfg['sunY'], W * 0.20, $cfg['sunCore'], 0.60, 1.0, 1.8);

    // thành phố mờ phía xa "bên kia sông" (chỉ dùng cho bìa có thêm thể loại Đô Thị).
    // Cố ý để rất nhạt + phủ sương dày cho hòa vào nền mơ màng, không cắt cứng.
    if (! empty($cfg['skyline'])) {
        skyline($img, H * 0.875, 26, 104, hex($cfg['skylineTop']), hex($cfg['skylineBot']), 0.34, $cfg['window'], 0.34, 0.26, true, 18, 46);
        fogBand($img, H * 0.845, 58 * SS, 12 * SS, $cfg['haze'], 0.34);
        fogBand($img, H * 0.878, 30 * SS, 6 * SS, $cfg['haze'], 0.40);
        // vệt sáng mặt sông ngay dưới chân thành phố
        fogBand($img, H * 0.905, 16 * SS, 4 * SS, hex('#FFF0D6'), 0.30);
    }

    bokehField($img, $cfg['bokeh'], $cfg['bokehPalette'], 0.30, 12, 78);

    // cánh hoa rơi
    for ($i = 0; $i < $cfg['petals']; $i++) {
        $x = mt_rand(0, W);
        $y = mt_rand(0, H);
        $scale = 0.45 + 1.05 * pow(mt_rand(0, 100) / 100, 1.4);
        $rx = 13 * $scale * SS;
        $ry = 6.4 * $scale * SS;
        $ang = mt_rand(0, 628) / 100;
        $c = $cfg['petalPalette'][mt_rand(0, count($cfg['petalPalette']) - 1)];
        $a = 0.30 + 0.55 * (mt_rand(0, 100) / 100);

        softPetal($img, (float) $x, (float) $y, $rx, $ry, $ang, $c, $a * 0.85);
        // chút sáng ở mép cánh
        softPetal($img, $x - cos($ang) * $rx * 0.28, $y - sin($ang) * $rx * 0.28, $rx * 0.5, $ry * 0.55, $ang, [255, 255, 255], $a * 0.16);
    }

    // bụi sáng li ti
    starField($img, 130, H * 0.98, [255, 246, 240], 0.45);
}

/** Linh Dị: sương mù nhiều lớp, trăng lạnh, bóng cây khẳng khiu. */
function motifHorror($img, array $cfg): void
{
    gradientFill($img, $cfg['sky']);
    starField($img, 90, H * 0.5, $cfg['starColor'], 0.4);

    drawMoon($img, W * $cfg['moonX'], H * $cfg['moonY'], W * $cfg['moonR'], $cfg['moonCore'], $cfg['moonGlow'], 0.40);

    // rặng cây xa mờ
    $far = makeRidge(H * 0.70, H * 0.10, 14, false, true, 1.4);
    fillTerrain($img, $far, hex($cfg['farTop']), hex($cfg['farBot']), 0.55);
    fogBand($img, H * 0.66, 60 * SS, 26 * SS, $cfg['fog'], 0.26);

    // cây khẳng khiu
    foreach ($cfg['trees'] as [$tx, $ty, $ang, $len, $thick, $depth, $alpha]) {
        branch($img, W * $tx, H * $ty, $ang, H * $len, $thick * SS, $depth, $cfg['tree'], $alpha);
    }

    // mặt đất
    $ground = makeRidge(H * 0.955, H * 0.03, 10, false, true, 1.0);
    fillTerrain($img, $ground, hex($cfg['groundTop']), hex($cfg['groundBot']), 0.92);

    // nhiều lớp sương chồng nhau
    foreach ($cfg['fogBands'] as [$y, $th, $wob, $a]) {
        fogBand($img, H * $y, $th * SS, $wob * SS, $cfg['fog'], $a);
    }

    // đốm ma trơi
    for ($i = 0; $i < 16; $i++) {
        $x = mt_rand(0, W);
        $y = mt_rand((int) (H * 0.55), (int) (H * 0.95));
        $r = (mt_rand(8, 20) / 10) * SS;
        softDisc($img, (float) $x, (float) $y, $r, $cfg['wisp'], 0.55, 0.9, 1.3);
        softDisc($img, (float) $x, (float) $y, $r * 9, $cfg['wisp'], 0.10, 1.0, 2.3);
    }
}

/** Đam Mỹ: gradient tím-hồng dịu + vòng tròn đồng tâm. */
function motifConcentric($img, array $cfg): void
{
    gradientFill($img, $cfg['sky']);
    starField($img, 300, H * 0.86, hex('#FFF2FF'), 0.55);

    $cx = W * $cfg['cx'];
    $cy = H * $cfg['cy'];

    softDisc($img, $cx, $cy, W * 1.0, $cfg['halo'], 0.34, 1.0, 2.3);

    // Vòng đồng tâm kiểu gợn sóng: khoảng cách giãn dần, đa số rất mảnh,
    // thỉnh thoảng một vòng đậm làm điểm nhấn -> tránh cảm giác "bia bắn".
    $rings = $cfg['rings'];
    for ($i = 0; $i < $rings; $i++) {
        $t = $i / max(1, $rings - 1);
        $radius = W * (0.075 + 0.80 * pow($t, 1.5));
        $accent = ($i % 4 === 1);
        $thick = SS * lerp(2.4, 0.85, $t) * ($accent ? 2.4 : 1.0);
        $c = lerpColor($cfg['ringA'], $cfg['ringB'], pow($t, 0.75));
        $a = lerp(0.40, 0.08, $t) * ($accent ? 1.0 : 0.42);
        softRing($img, $cx, $cy, $radius, $thick, $c, $a);
    }

    // lõi sáng
    softDisc($img, $cx, $cy, W * 0.16, $cfg['core'], 0.34, 1.0, 2.0);
    softDisc($img, $cx, $cy, W * 0.052, $cfg['core'], 0.62, 1.0, 1.5);
    softDisc($img, $cx, $cy, W * 0.016, $cfg['core'], 0.90, 0.9, 1.2);

    // dải sáng mềm cắt ngang cho đỡ đơn điệu
    fogBand($img, H * 0.26, 70 * SS, 28 * SS, $cfg['veil'], 0.14);
    fogBand($img, H * 0.66, 96 * SS, 34 * SS, $cfg['veil'], 0.16);

    bokehField($img, 22, $cfg['bokehPalette'], 0.16, 10, 48);

    // rặng núi mờ ở đáy: neo bố cục lại, giữ chất Tiên Hiệp
    $far = makeRidge(H * 0.90, H * 0.11, 9, false, true, 1.2);
    fillTerrain($img, $far, hex($cfg['ridgeFarTop']), hex($cfg['ridgeFarBot']), 0.62);
    fogBand($img, H * 0.88, 34 * SS, 14 * SS, $cfg['veil'], 0.20);

    $near = makeRidge(H * 1.02, H * 0.12, 7, false, true, 1.1);
    fillTerrain($img, $near, hex($cfg['ridgeNearTop']), hex($cfg['ridgeNearBot']), 0.95);
}

/* ────────────────────────────── Hậu kỳ ────────────────────────────── */

/** Tối góc (vignette) — làm trên ảnh đã thu nhỏ. */
function vignette($img, int $w, int $h, float $strength, float $inner = 0.55): void
{
    $cx = $w / 2;
    $cy = $h / 2;
    $max = sqrt($cx * $cx + $cy * $cy);

    for ($y = 0; $y < $h; $y++) {
        $dy = ($y - $cy);
        for ($x = 0; $x < $w; $x++) {
            $dx = ($x - $cx);
            $d = sqrt($dx * $dx + $dy * $dy) / $max;
            $f = smoothstep($inner, 1.0, $d);
            if ($f <= 0) {
                continue;
            }
            $a = $strength * $f;
            $c = imagecolorat($img, $x, $y);
            $r = (int) ((($c >> 16) & 0xFF) * (1 - $a));
            $g = (int) ((($c >> 8) & 0xFF) * (1 - $a));
            $b = (int) (($c & 0xFF) * (1 - $a));
            imagesetpixel($img, $x, $y, ($r << 16) | ($g << 8) | $b);
        }
    }
}

/** Hạt nhiễu mịn cho ảnh bớt "phẳng" kiểu vector. */
function grain($img, int $w, int $h, int $amount): void
{
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $n = mt_rand(-$amount, $amount);
            $c = imagecolorat($img, $x, $y);
            $r = max(0, min(255, (($c >> 16) & 0xFF) + $n));
            $g = max(0, min(255, (($c >> 8) & 0xFF) + $n));
            $b = max(0, min(255, ($c & 0xFF) + $n));
            imagesetpixel($img, $x, $y, ($r << 16) | ($g << 8) | $b);
        }
    }
}

/* ────────────────────────────── Cấu hình từng truyện ────────────────────────────── */

/**
 * 10 slug khớp với StorySeeder (Str::slug của tiêu đề).
 * Mỗi truyện chọn motif theo thể loại; truyện cùng thể loại đổi màu + bố cục.
 */
$covers = [

    // Tiên Hiệp + Huyền Huyễn — núi chồng lớp, trăng lớn bên phải, tông tím đế vương
    'cuu-chuyen-kiem-de' => ['mountain', [
        // dải ấm dồn lên quanh y≈0.5 để lọt vào khoảng trời phía trên rặng núi
        'sky' => [
            [0.00, hex('#07050F')],
            [0.18, hex('#150D30')],
            [0.32, hex('#2A1550')],
            [0.44, hex('#5A2668')],
            [0.52, hex('#9E4478')],
            [0.60, hex('#D9787E')],
            [0.72, hex('#EDA487')],
            [1.00, hex('#F6C9A2')],
        ],
        'stars' => 620, 'starColor' => hex('#EFE7FF'),
        'glow' => hex('#F0A184'), 'glowX' => 0.62, 'glowY' => 0.55, 'glowR' => 0.85,
        'moonX' => 0.70, 'moonY' => 0.24, 'moonR' => 0.175,
        'moonCore' => hex('#FFF3DC'), 'moonGlow' => hex('#FFD9A8'),
        'cloud' => hex('#C88FB8'),
        'clouds' => [[0.27, 9, 20, 0.22], [0.34, 6, 26, 0.16], [0.20, 5, 18, 0.12]],
        'mist' => hex('#C9A0D8'),
        'layers' => [
            [0.66, 0.16, 9,  '#4B2A63', '#3A2153', 0.62],
            [0.75, 0.19, 7,  '#341C4C', '#28153B', 0.78],
            [0.85, 0.22, 6,  '#22112F', '#180B22', 0.90],
            [0.98, 0.20, 5,  '#120818', '#0A040E', 1.00],
        ],
    ]],

    // Tiên Hiệp — núi chồng lớp, trăng lớn bên trái, tông ngọc bích / thanh u
    'pham-nhan-tu-tien-lo' => ['mountain', [
        'sky' => [
            [0.00, hex('#04101A')],
            [0.18, hex('#07202C')],
            [0.32, hex('#0B3540')],
            [0.42, hex('#135A54')],
            [0.50, hex('#2C8A6E')],
            [0.58, hex('#63B58C')],
            [0.70, hex('#9AD2A6')],
            [1.00, hex('#C8E8C6')],
        ],
        'stars' => 540, 'starColor' => hex('#E4FBF3'),
        'glow' => hex('#7FD3AE'), 'glowX' => 0.38, 'glowY' => 0.50, 'glowR' => 0.80,
        'moonX' => 0.27, 'moonY' => 0.20, 'moonR' => 0.145,
        'moonCore' => hex('#F2FFF8'), 'moonGlow' => hex('#A9F0D6'),
        'cloud' => hex('#7FC7BC'),
        'clouds' => [[0.23, 7, 24, 0.20], [0.31, 5, 30, 0.14], [0.42, 8, 20, 0.12]],
        'mist' => hex('#A8E4D4'),
        'layers' => [
            [0.60, 0.13, 11, '#2C5A5C', '#204547', 0.55],
            [0.70, 0.17, 8,  '#1B4245', '#143134', 0.72],
            [0.81, 0.21, 6,  '#102A2E', '#0A1D21', 0.88],
            [0.97, 0.19, 5,  '#07171A', '#030C0E', 1.00],
        ],
    ]],

    // Huyền Huyễn + Kiếm Hiệp — núi nhọn, trăng huyết, vệt chém từ trên trái xuống dưới phải
    'de-ba-thuong-khung' => ['sword', [
        'sky' => [
            [0.00, hex('#0E0308')],
            [0.16, hex('#26070F')],
            [0.30, hex('#48101A')],
            [0.40, hex('#7B2320')],
            [0.48, hex('#B8461F')],
            [0.56, hex('#E07A33')],
            [0.68, hex('#F0A855')],
            [1.00, hex('#F7C87A')],
        ],
        'stars' => 380, 'starColor' => hex('#FFE7D2'),
        'glow' => hex('#FF8A3C'), 'glowX' => 0.50, 'glowY' => 0.47, 'glowR' => 0.80,
        'moonX' => 0.72, 'moonY' => 0.19, 'moonR' => 0.155,
        'moonCore' => hex('#FFD9A0'), 'moonGlow' => hex('#FF7A4E'),
        'crescent' => false,
        'mist' => hex('#E08A5A'),
        'layers' => [
            [0.72, 0.30, 6, '#54202A', '#3C141D', 0.72],
            [0.86, 0.34, 5, '#2C0E16', '#1A060C', 0.90],
            [1.00, 0.26, 4, '#150409', '#0A0104', 1.00],
        ],
        'slashGlow' => hex('#FFB067'), 'slashCore' => hex('#FFF6E4'),
        'slashes' => [
            [-0.05, 0.10, 1.05, 0.72, 1.0],
            [0.10, -0.04, 1.02, 0.42, 0.45],
        ],
    ]],

    // Kiếm Hiệp + Tiên Hiệp — núi nhọn, trăng khuyết, vệt chém ngược từ dưới trái lên, tông thép lạnh
    'kiem-lai' => ['sword', [
        'sky' => [
            [0.00, hex('#04060E')],
            [0.18, hex('#0A1322')],
            [0.32, hex('#142438')],
            [0.42, hex('#234058')],
            [0.52, hex('#3E6B8E')],
            [0.62, hex('#6E9CBC')],
            [0.74, hex('#A8C8DC')],
            [1.00, hex('#CDDFEC')],
        ],
        'stars' => 460, 'starColor' => hex('#E8F1FF'),
        'glow' => hex('#88B4E0'), 'glowX' => 0.30, 'glowY' => 0.48, 'glowR' => 0.80,
        'moonX' => 0.30, 'moonY' => 0.22, 'moonR' => 0.135,
        'moonCore' => hex('#F4F9FF'), 'moonGlow' => hex('#9FC8F5'),
        'crescent' => true,
        'mist' => hex('#A9C4DD'),
        'layers' => [
            [0.70, 0.28, 7, '#2C3E56', '#1E2B3D', 0.66],
            [0.84, 0.33, 5, '#182436', '#0E1725', 0.88],
            [1.00, 0.25, 4, '#0A101C', '#04070E', 1.00],
        ],
        'slashGlow' => hex('#9FD4FF'), 'slashCore' => hex('#FFFFFF'),
        'slashes' => [
            [-0.04, 0.86, 1.04, 0.16, 1.0],
            [-0.02, 0.98, 0.90, 0.44, 0.4],
        ],
    ]],

    // Đô Thị + Trọng Sinh — skyline hoàng hôn xanh ngọc, cửa sổ hổ phách
    'cuc-pham-than-y' => ['city', [
        'sky' => [
            [0.00, hex('#040D18')],
            [0.18, hex('#071B28')],
            [0.32, hex('#0B2E3A')],
            [0.44, hex('#10505A')],
            [0.54, hex('#1E7C7C')],
            [0.64, hex('#3FA79B')],
            [0.76, hex('#74C9B4')],
            [1.00, hex('#A5E0C8')],
        ],
        'stars' => 260, 'starColor' => hex('#DFF6FF'),
        'glow' => hex('#4FD1C5'), 'glowX' => 0.68, 'glowY' => 0.58, 'glowR' => 0.95,
        'glow2' => hex('#2E7FA8'),
        'moon' => true, 'moonX' => 0.24, 'moonY' => 0.16, 'moonR' => 0.085,
        'moonCore' => hex('#EFFBFF'), 'moonGlow' => hex('#8FE3E0'),
        'window' => hex('#FFD68A'),
        'haze' => hex('#7FD8CE'),
        'bands' => [
            // baseY, minH, maxH, cTop, cBot, alpha, winAlpha, winChance, windows, minW, maxW
            [0.78, 40, 170, '#123845', '#0C2733', 0.55, 0.26, 0.30, true, 22, 52],
            [0.88, 60, 250, '#0B2029', '#06161D', 0.82, 0.55, 0.42, true, 26, 62],
            [1.00, 70, 220, '#050E13', '#020607', 1.00, 0.70, 0.34, true, 34, 78],
        ],
    ]],

    // Đô Thị + Trọng Sinh — skyline đêm chàm/đỏ tía, nhà cao chọc trời, đèn lạnh
    'trong-sinh-chi-do-thi-cuong-long' => ['city', [
        'sky' => [
            [0.00, hex('#06061A')],
            [0.16, hex('#0C0E30')],
            [0.28, hex('#171448')],
            [0.38, hex('#2E1A5C')],
            [0.46, hex('#55276E')],
            [0.54, hex('#8A3576')],
            [0.64, hex('#C1587E')],
            [0.78, hex('#E08A8A')],
            [1.00, hex('#F0B79C')],
        ],
        'stars' => 300, 'starColor' => hex('#EDE9FF'),
        'glow' => hex('#E0568C'), 'glowX' => 0.34, 'glowY' => 0.47, 'glowR' => 0.90,
        'glow2' => hex('#5B4BD6'),
        'moon' => false, 'moonX' => 0.8, 'moonY' => 0.15, 'moonR' => 0.08,
        'moonCore' => hex('#FFFFFF'), 'moonGlow' => hex('#FFFFFF'),
        'window' => hex('#FFE1A6'),
        'haze' => hex('#B77FD8'),
        'bands' => [
            [0.74, 60, 250, '#2A1E4E', '#1B1338', 0.50, 0.24, 0.26, true, 18, 44],
            [0.86, 90, 380, '#180F33', '#0E0821', 0.80, 0.52, 0.40, true, 24, 54],
            [1.00, 110, 330, '#0A0518', '#04020A', 1.00, 0.68, 0.32, true, 30, 70],
        ],
    ]],

    // Ngôn Tình — bokeh hồng phấn, cánh hoa rơi, nắng chiều bên phải
    'thinh-the-ngon-tinh' => ['romance', [
        'sky' => [
            [0.00, hex('#3A1140')],
            [0.22, hex('#6E1E55')],
            [0.46, hex('#B24170')],
            [0.68, hex('#E4738A')],
            [0.86, hex('#F7A98F')],
            [1.00, hex('#FFD9B0')],
        ],
        'sunX' => 0.72, 'sunY' => 0.30,
        'sunGlow' => hex('#FFC9A8'), 'sunCore' => hex('#FFF2DC'),
        'bokeh' => 78,
        'bokehPalette' => [hex('#FFD9E6'), hex('#FFB4C8'), hex('#FFE7C4'), hex('#FFFFFF'), hex('#F6A8D0')],
        'petals' => 62,
        'petalPalette' => [hex('#FFC2D6'), hex('#FF9FBE'), hex('#FFE0E9'), hex('#F58BAE')],
        'haze' => hex('#FFD9C8'),
        'window' => hex('#FFE4B5'),
        'skyline' => false,
    ]],

    // Ngôn Tình + Đô Thị — bokeh cam san hô, có bóng thành phố mờ bên kia sông
    'hoa-no-ben-kia-song' => ['romance', [
        'sky' => [
            [0.00, hex('#1C2140')],
            [0.20, hex('#42315C')],
            [0.44, hex('#8A4F6A')],
            [0.64, hex('#D07A6C')],
            [0.82, hex('#F2A377')],
            [1.00, hex('#FBD9A6')],
        ],
        'sunX' => 0.30, 'sunY' => 0.42,
        'sunGlow' => hex('#FFB98A'), 'sunCore' => hex('#FFF0D2'),
        'bokeh' => 62,
        'bokehPalette' => [hex('#FFD2A8'), hex('#FFB98F'), hex('#FFE9CE'), hex('#FFFFFF'), hex('#E9909B')],
        'petals' => 48,
        'petalPalette' => [hex('#FFCDB2'), hex('#FFB49A'), hex('#FFE6D2'), hex('#EF9A8E')],
        'haze' => hex('#FFCEA8'),
        'window' => hex('#FFDFA8'),
        'skyline' => true,
        'skylineTop' => '#6E4A62', 'skylineBot' => '#4E3048',
    ]],

    // Linh Dị — sương mù nhiều lớp, trăng lạnh, cây khẳng khiu
    'u-minh-quy-su' => ['horror', [
        'sky' => [
            [0.00, hex('#04040A')],
            [0.26, hex('#0A0A18')],
            [0.52, hex('#141428')],
            [0.72, hex('#1E2038')],
            [0.88, hex('#2A2D46')],
            [1.00, hex('#3A3E58')],
        ],
        'starColor' => hex('#C6CBE6'),
        'moonX' => 0.63, 'moonY' => 0.21, 'moonR' => 0.125,
        'moonCore' => hex('#DDE4F0'), 'moonGlow' => hex('#8E9CC4'),
        'farTop' => '#141628', 'farBot' => '#0B0C18',
        'groundTop' => '#0A0A14', 'groundBot' => '#040408',
        'fog' => hex('#9AA4C4'),
        'tree' => hex('#05050C'),
        'wisp' => hex('#9FE8D8'),
        'trees' => [
            [0.14, 0.99, -1.48, 0.30, 5.0, 6, 0.95],
            [0.86, 0.99, -1.66, 0.26, 4.2, 6, 0.92],
            [0.42, 1.00, -1.52, 0.17, 3.0, 5, 0.80],
        ],
        'fogBands' => [
            [0.55, 40, 30, 0.14],
            [0.64, 46, 26, 0.20],
            [0.73, 54, 22, 0.26],
            [0.83, 64, 18, 0.32],
            [0.93, 74, 14, 0.36],
        ],
    ]],

    // Tiên Hiệp + Đam Mỹ — gradient tím-hồng dịu + vòng tròn đồng tâm
    'truong-sinh-bat-tu-kinh' => ['concentric', [
        'sky' => [
            [0.00, hex('#0B0722')],
            [0.24, hex('#1B0F3C')],
            [0.48, hex('#3A1A5E')],
            [0.70, hex('#6B2E7C')],
            [0.86, hex('#A5458C')],
            [1.00, hex('#D97BA0')],
        ],
        'cx' => 0.50, 'cy' => 0.38,
        'halo' => hex('#C77BE8'),
        'ringA' => hex('#FFE3F4'), 'ringB' => hex('#7C6BF0'),
        'core' => hex('#FFF0FA'),
        'veil' => hex('#D9A8F0'),
        'bokehPalette' => [hex('#E9C6FF'), hex('#FFC9E6'), hex('#B49CFF'), hex('#FFFFFF')],
        'rings' => 15,
        'ridgeFarTop' => '#5A2A6E', 'ridgeFarBot' => '#3E1A52',
        'ridgeNearTop' => '#26103A', 'ridgeNearBot' => '#140820',
    ]],
];

/* ────────────────────────────── Vòng lặp sinh ảnh ────────────────────────────── */

$outDir = __DIR__;
$t0 = microtime(true);

foreach ($covers as $slug => [$motif, $cfg]) {
    // Ngẫu nhiên TẤT ĐỊNH theo slug -> chạy lại ra ảnh y hệt.
    mt_srand(crc32($slug), MT_RAND_MT19937);

    $img = imagecreatetruecolor(W, H);
    imagealphablending($img, true);
    imageantialias($img, true);

    match ($motif) {
        'mountain' => motifMountain($img, $cfg),
        'sword' => motifSword($img, $cfg),
        'city' => motifCity($img, $cfg),
        'romance' => motifRomance($img, $cfg),
        'horror' => motifHorror($img, $cfg),
        'concentric' => motifConcentric($img, $cfg),
    };

    // Thu nhỏ về 600x800 -> khử răng cưa toàn ảnh
    $out = imagecreatetruecolor(OUT_W, OUT_H);
    imagealphablending($out, true);
    imagecopyresampled($out, $img, 0, 0, 0, 0, OUT_W, OUT_H, W, H);
    imagedestroy($img);

    vignette($out, OUT_W, OUT_H, $motif === 'romance' ? 0.22 : 0.34, 0.50);
    grain($out, OUT_W, OUT_H, $motif === 'horror' ? 5 : 3);

    $path = $outDir.'/'.$slug.'.jpg';
    imagejpeg($out, $path, 90);
    imagedestroy($out);

    printf("  ✓ %-34s %s  (%d KB)\n", $slug, $motif, (int) round(filesize($path) / 1024));
}

printf("Xong: %d artwork (600x800, JPEG q90) tại %s — %.1fs\n", count($covers), $outDir, microtime(true) - $t0);

<?php

declare(strict_types=1);

/**
 * Sinh ARTWORK bìa truyện cho seed data — vẽ 100% bằng PHP GD, KHÔNG CÓ BẤT KỲ CHỮ NÀO.
 *
 * Mô-típ: ĐÔ THỊ / GIÀU SANG — nhân vật bị coi thường vì trông nghèo rồi lộ ra là
 * chủ tịch / thừa kế cực giàu. Ngôn ngữ hình ảnh: tháp kính, cửa sổ vàng kim, đèn thành phố,
 * bóng người đơn độc dưới vệt đèn rọi, bão trên nóc phố, dinh thự, hừng đông.
 *
 * Tên truyện / tác giả do app tự hiển thị đè lên, nên ảnh chỉ là artwork thuần.
 *
 * Chạy lại khi muốn đổi thiết kế bìa:
 *   php database/seeders/covers/generate-covers.php
 *
 * Đặc điểm kỹ thuật (giữ nguyên như bản trước):
 *  - Vẽ ở 1200x1600 (siêu lấy mẫu x2) rồi thu nhỏ về 600x800 -> khử răng cưa toàn ảnh.
 *  - Gradient nhiều điểm dừng + nội suy smoothstep.
 *  - Mọi hình mềm (quầng sáng, bokeh, sương, bóng người, tia sét) đều tự trộn alpha theo pixel
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

/** Số ngẫu nhiên 0..1 (tất định theo mt_srand). */
function rnd(): float
{
    return mt_rand(0, 10000) / 10000;
}

/** Số ngẫu nhiên trong [$a, $b]. */
function rndf(float $a, float $b): float
{
    return $a + ($b - $a) * rnd();
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

/** Ellipse mềm có xoay — vũng đèn, bóng đổ, tán cây. */
function softEllipse($img, float $cx, float $cy, float $rx, float $ry, float $angle, array $color, float $alpha, float $edge = 0.10, float $power = 1.0): void
{
    $rad = max($rx, $ry) + 2;
    $x0 = (int) max(0, floor($cx - $rad));
    $x1 = (int) min(W - 1, ceil($cx + $rad));
    $y0 = (int) max(0, floor($cy - $rad));
    $y1 = (int) min(H - 1, ceil($cy + $rad));
    $cos = cos(-$angle);
    $sin = sin(-$angle);
    $inner = max(0.0, 1.0 - max($edge, 1.6 * SS / max($rx, $ry)));

    for ($y = $y0; $y <= $y1; $y++) {
        for ($x = $x0; $x <= $x1; $x++) {
            $dx = $x - $cx;
            $dy = $y - $cy;
            $u = $dx * $cos - $dy * $sin;
            $v = $dx * $sin + $dy * $cos;
            $q = sqrt(($u / $rx) * ($u / $rx) + ($v / $ry) * ($v / $ry));
            if ($q >= 1.0) {
                continue;
            }
            $f = 1.0 - smoothstep($inner, 1.0, $q);
            if ($power !== 1.0) {
                $f = pow($f, $power);
            }
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

/** Dải sương / khói đèn ngang uốn lượn (tổng các sóng sin tất định). */
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

/** Kiểm tra điểm nằm trong đa giác (ray casting). */
function pointInPoly(array $pts, float $x, float $y): bool
{
    $inside = false;
    $n = count($pts);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $pts[$i][0];
        $yi = $pts[$i][1];
        $xj = $pts[$j][0];
        $yj = $pts[$j][1];
        if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi) {
            $inside = ! $inside;
        }
    }

    return $inside;
}

/** Đa giác mềm (khử răng cưa bằng 2x2 mẫu con), tô gradient dọc. */
function softPoly($img, array $pts, array $cTop, array $cBot, float $alpha, ?float $yTop = null, ?float $yBot = null): void
{
    $minx = $maxx = $pts[0][0];
    $miny = $maxy = $pts[0][1];
    foreach ($pts as [$x, $y]) {
        $minx = min($minx, $x);
        $maxx = max($maxx, $x);
        $miny = min($miny, $y);
        $maxy = max($maxy, $y);
    }
    $yTop ??= $miny;
    $yBot ??= $maxy;
    $span = max(1.0, $yBot - $yTop);

    $x0 = (int) max(0, floor($minx));
    $x1 = (int) min(W - 1, ceil($maxx));
    $y0 = (int) max(0, floor($miny));
    $y1 = (int) min(H - 1, ceil($maxy));
    $sub = [[0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]];

    for ($y = $y0; $y <= $y1; $y++) {
        $c = lerpColor($cTop, $cBot, clamp01(($y - $yTop) / $span));
        for ($x = $x0; $x <= $x1; $x++) {
            $cov = 0.0;
            foreach ($sub as [$ox, $oy]) {
                if (pointInPoly($pts, $x + $ox, $y + $oy)) {
                    $cov += 0.25;
                }
            }
            if ($cov <= 0) {
                continue;
            }
            px($img, $x, $y, $c, $alpha * $cov);
        }
    }
}

/** Chữ nhật gradient dọc (nhanh hơn softPoly cho khối chữ nhật lớn). */
function rectV($img, float $x0, float $y0, float $x1, float $y1, array $cTop, array $cBot, float $alpha): void
{
    $ix0 = (int) max(0, floor($x0));
    $ix1 = (int) min(W - 1, ceil($x1));
    $iy0 = (int) max(0, floor($y0));
    $iy1 = (int) min(H - 1, ceil($y1));
    $span = max(1.0, $y1 - $y0);

    for ($y = $iy0; $y <= $iy1; $y++) {
        $c = lerpColor($cTop, $cBot, clamp01(($y - $y0) / $span));
        for ($x = $ix0; $x <= $ix1; $x++) {
            px($img, $x, $y, $c, $alpha);
        }
    }
}

/* ────────────────────────────── Nhiễu 1D cho đường chân trời mềm ────────────────────────────── */

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

/** Hàm chiều cao của một gò đất / rặng cây xa. */
function makeRidge(float $baseY, float $amp, int $pts, bool $smooth = true, float $sharp = 1.0): callable
{
    $a = noiseSeries($pts);
    $b = noiseSeries($pts * 3);

    return function (int $x) use ($a, $b, $baseY, $amp, $smooth, $sharp) {
        $t = $x / (W - 1);
        $v = 0.74 * noiseAt($a, $t, $smooth) + 0.26 * noiseAt($b, $t, $smooth);
        if ($sharp !== 1.0) {
            $v = pow(clamp01($v), $sharp);
        }

        return $baseY - $amp * $v;
    };
}

/** Tô khối từ đường chân trên xuống đáy ảnh, gradient trong thân + AA mép trên. */
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

/* ────────────────────────────── Thành phần đô thị ────────────────────────────── */

/** Sao li ti — dày ở trên, thưa dần xuống dưới. */
function starField($img, int $count, float $maxY, array $color, float $baseAlpha = 0.9): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(0, W - 1);
        $y = mt_rand(0, (int) $maxY);
        $depth = 1.0 - ($y / max($maxY, 1));
        if (rnd() > 0.25 + 0.75 * $depth) {
            continue;
        }
        $a = $baseAlpha * (0.25 + 0.75 * rnd()) * (0.35 + 0.65 * $depth);
        $r = rndf(0.6, 1.75) * SS * 0.55;
        softDisc($img, (float) $x, (float) $y, $r, $color, $a, 0.9, 1.2);

        if (mt_rand(0, 100) < 6) {
            softDisc($img, (float) $x, (float) $y, $r * 7, $color, $a * 0.22, 1.0, 2.2);
        }
    }
}

/** Một ô cửa sổ sáng + quầng hắt ra kính. */
function litWindow($img, float $x, float $y, float $w, float $h, array $color, float $alpha, float $glowChance = 0.18, float $glowScale = 3.6): void
{
    $ex = (int) ($x + $w);
    $ey = (int) ($y + $h);
    for ($yy = (int) $y; $yy < $ey; $yy++) {
        for ($xx = (int) $x; $xx < $ex; $xx++) {
            px($img, $xx, $yy, $color, $alpha);
        }
    }
    if (rnd() < $glowChance) {
        softDisc($img, $x + $w / 2, $y + $h / 2, max($w, $h) * $glowScale, $color, $alpha * 0.20, 1.0, 2.1);
    }
}

/** Lưới cửa sổ sáng trong một vùng chữ nhật. */
function windowGrid($img, float $x0, float $y0, float $x1, float $y1, float $cw, float $ch, float $gx, float $gy, array $color, float $alpha, float $chance, float $glowChance = 0.18): void
{
    if ($x1 - $x0 < $cw || $y1 - $y0 < $ch) {
        return;
    }
    for ($wy = $y0; $wy + $ch <= $y1; $wy += $ch + $gy) {
        for ($wx = $x0; $wx + $cw <= $x1; $wx += $cw + $gx) {
            if (rnd() > $chance) {
                continue;
            }
            litWindow($img, $wx, $wy, $cw, $ch, $color, $alpha * (0.45 + 0.55 * rnd()), $glowChance);
        }
    }
}

/**
 * Một dải nhà chạy ngang khung hình: khối cao thấp ngẫu nhiên, chóp lùi / chóp vát,
 * cột ăng-ten và lưới cửa sổ sáng.
 * Chiều cao tính theo tỉ lệ H, bề ngang theo tỉ lệ W.
 */
function skylineBand($img, array $c): void
{
    $baseY = $c['baseY'] * H;
    $cTop = $c['cTop'];
    $cBot = $c['cBot'];
    $alpha = $c['alpha'];
    $win = $c['win'];
    $winAlpha = $c['winAlpha'];
    $winChance = $c['winChance'];
    $ws = $c['winScale'] ?? 1.0;
    $gap = $c['gap'] ?? 0.010;

    $x = ($c['startX'] ?? -0.06) * W;
    $end = ($c['endX'] ?? 1.06) * W;

    while ($x < $end) {
        $bw = lerp($c['minW'], $c['maxW'], rnd()) * W;
        $bh = lerp($c['minH'], $c['maxH'], pow(rnd(), 1.55)) * H;
        $top = $baseY - $bh;
        $l = $x;
        $r = $x + $bw;

        softPoly($img, [[$l, $top], [$r, $top], [$r, $baseY], [$l, $baseY]], $cTop, $cBot, $alpha, $top, $baseY);

        $roll = mt_rand(0, 100);
        if ($roll < 22 && $bh > 0.10 * H) {                     // khối lùi trên nóc
            $ins = $bw * 0.20;
            $h2 = $bh * rndf(0.10, 0.24);
            softPoly($img, [[$l + $ins, $top - $h2], [$r - $ins, $top - $h2], [$r - $ins, $top + 1], [$l + $ins, $top + 1]],
                $cTop, $cTop, $alpha, $top - $h2, $top);
        } elseif ($roll < 36 && $bh > 0.08 * H) {               // chóp vát
            $h2 = $bh * rndf(0.08, 0.18);
            softPoly($img, [[$l, $top + 1], [$r, $top + 1], [$r - $bw * 0.32, $top - $h2], [$l + $bw * 0.32, $top - $h2]],
                $cTop, $cTop, $alpha, $top - $h2, $top);
        }

        if (mt_rand(0, 100) < 28 && $bh > 0.11 * H) {           // cột ăng-ten + đèn báo không
            $ax = $l + $bw * rndf(0.30, 0.70);
            $ah = rndf(0.018, 0.055) * H;
            softSegment($img, $ax, $top, $ax, $top - $ah, 0.8 * SS, 1.1 * SS, $cTop, $alpha);
            if (mt_rand(0, 100) < 55) {
                softDisc($img, $ax, $top - $ah, 2.0 * SS, $win, $winAlpha * 0.9, 0.9, 1.4);
                softDisc($img, $ax, $top - $ah, 7.0 * SS, $win, $winAlpha * 0.18, 1.0, 2.2);
            }
        }

        windowGrid($img,
            $l + 5 * SS, $top + 7 * SS, $r - 5 * SS, $baseY - 5 * SS,
            3 * SS * $ws, 4 * SS * $ws, 5 * SS * $ws, 7 * SS * $ws,
            $win, $winAlpha, $winChance
        );

        $x = $r + lerp(0.0015, $gap, rnd()) * W;
    }
}

/**
 * Tháp kính "nhân vật chính": thân vát nhẹ, mặt kính có sọc dọc, cửa sổ vàng kim,
 * cạnh sáng phản chiếu, đỉnh có quầng sáng + cột thu lôi.
 */
function heroTower($img, array $c): void
{
    $cx = $c['x'] * W;
    $baseY = $c['baseY'] * H;
    $wB = $c['w'] * W;
    $wT = $wB * ($c['taper'] ?? 0.78);
    $topY = $baseY - $c['h'] * H;
    $alpha = $c['alpha'] ?? 1.0;

    softPoly($img, [
        [$cx - $wT / 2, $topY], [$cx + $wT / 2, $topY],
        [$cx + $wB / 2, $baseY], [$cx - $wB / 2, $baseY],
    ], $c['cTop'], $c['cBot'], $alpha, $topY, $baseY);

    $span = max(1.0, $baseY - $topY);
    $halfAt = fn (float $y) => lerp($wT, $wB, clamp01(($y - $topY) / $span)) / 2;

    // sọc kính dọc
    $mn = $c['mullions'] ?? 4;
    for ($i = 1; $i <= $mn; $i++) {
        $f = $i / ($mn + 1);
        $x1 = $cx - $wT / 2 + $wT * $f;
        $x2 = $cx - $wB / 2 + $wB * $f;
        softSegment($img, $x1, $topY, $x2, $baseY, 0.6 * SS, 1.6 * SS, $c['glass'], ($c['glassAlpha'] ?? 0.12));
    }

    // cửa sổ sáng, mật độ có thể tăng dần lên đỉnh
    $cw = ($c['winW'] ?? 3) * SS;
    $ch = ($c['winH'] ?? 4) * SS;
    $gx = ($c['winGapX'] ?? 5) * SS;
    $gy = ($c['winGapY'] ?? 7) * SS;
    $chTop = $c['winChanceTop'] ?? $c['winChance'] ?? 0.4;
    $chBot = $c['winChanceBot'] ?? $c['winChance'] ?? 0.4;

    for ($y = $topY + 9 * SS; $y + $ch < $baseY - 6 * SS; $y += $ch + $gy) {
        $t = ($y - $topY) / $span;
        $hw = $halfAt($y) - 5 * SS;
        if ($hw <= $cw) {
            continue;
        }
        $chance = lerp($chTop, $chBot, $t);
        for ($x = $cx - $hw; $x + $cw < $cx + $hw; $x += $cw + $gx) {
            if (rnd() > $chance) {
                continue;
            }
            litWindow($img, $x, $y, $cw, $ch, $c['win'], ($c['winAlpha'] ?? 0.6) * (0.5 + 0.5 * rnd()), 0.16);
        }
    }

    // cạnh sáng (phản chiếu ánh đèn thành phố)
    $side = $c['litSide'] ?? 1;
    softSegment($img,
        $cx + $side * $wT / 2, $topY,
        $cx + $side * $wB / 2, $baseY,
        1.1 * SS, 3.4 * SS, $c['edge'], $c['edgeAlpha'] ?? 0.30
    );

    // đỉnh tháp
    softDisc($img, $cx, $topY, $wB * ($c['crownR'] ?? 1.1), $c['crown'] ?? $c['win'], $c['crownAlpha'] ?? 0.32, 1.0, 2.1);
    if ($c['spire'] ?? true) {
        $sh = ($c['spireH'] ?? 0.05) * H;
        softSegment($img, $cx, $topY + 2, $cx, $topY - $sh, 0.9 * SS, 1.3 * SS, $c['cTop'], $alpha);
        softDisc($img, $cx, $topY - $sh, 2.4 * SS, $c['crown'] ?? $c['win'], 0.85, 0.9, 1.3);
        softDisc($img, $cx, $topY - $sh, 12.0 * SS, $c['crown'] ?? $c['win'], 0.16, 1.0, 2.3);
    }
}

/** Mặt nước phản chiếu: soi ngược phần ảnh phía trên đường nước, gợn sóng + nhòe dần. */
function waterReflection($img, float $waterYF, float $strength, float $wobble, array $tint): void
{
    $wy = (int) round($waterYF * H);
    if ($wy >= H - 2) {
        return;
    }
    $depth = H - $wy;
    $ph = mt_rand(0, 628) / 100;

    for ($y = $wy; $y < H; $y++) {
        $d = $y - $wy;
        $src = $wy - (int) round($d * 0.94);
        if ($src < 1) {
            continue;
        }
        $fade = exp(-$d / ($depth * 0.55));
        $amp = $wobble * SS * (0.35 + 1.7 * ($d / $depth));
        for ($x = 0; $x < W; $x++) {
            $sx = (int) round($x + $amp * sin($y * 0.085 + $x * 0.011 + $ph));
            if ($sx < 0 || $sx >= W) {
                $sx = $x;
            }
            $c = imagecolorat($img, $sx, $src);
            $col = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
            px($img, $x, $y, lerpColor($col, $tint, 0.40), $strength * $fade);
        }
    }
}

/** Vệt sáng loang trên mặt nước. */
function waterStreaks($img, float $y0F, int $count, array $color, float $alphaMax): void
{
    for ($i = 0; $i < $count; $i++) {
        $y = lerp($y0F * H, H * 0.995, pow(rnd(), 0.8));
        $x = rndf(0.05, 0.95) * W;
        $len = rndf(0.03, 0.14) * W;
        $a = $alphaMax * (0.25 + 0.75 * rnd());
        softSegment($img, $x - $len / 2, $y, $x + $len / 2, $y, 0.8 * SS, 3.0 * SS, $color, $a, true);
    }
}

/** Nón đèn rọi từ trên xuống (đỉnh hẹp, chân loe). */
function lightBeam($img, float $apexXF, float $apexYF, float $x0F, float $x1F, float $baseYF, array $color, float $alpha, float $soft = 0.45): void
{
    $ax = $apexXF * W;
    $ay = $apexYF * H;
    $by = $baseYF * H;
    $bx0 = $x0F * W;
    $bx1 = $x1F * W;
    $y0 = (int) max(0, floor($ay));
    $y1 = (int) min(H - 1, ceil($by));

    for ($y = $y0; $y <= $y1; $y++) {
        $t = clamp01(($y - $ay) / max(1.0, $by - $ay));
        $l = lerp($ax, $bx0, $t);
        $r = lerp($ax, $bx1, $t);
        $cxl = ($l + $r) / 2;
        $hw = max(1.0, ($r - $l) / 2);
        $fall = 0.30 + 0.70 * pow(1.0 - $t, 0.9);
        $pad = $hw * $soft;
        $ix0 = (int) max(0, floor($cxl - $hw - $pad));
        $ix1 = (int) min(W - 1, ceil($cxl + $hw + $pad));
        for ($x = $ix0; $x <= $ix1; $x++) {
            $u = abs($x - $cxl) / ($hw + $pad);
            $f = 1.0 - smoothstep(1.0 - $soft, 1.0, $u);
            if ($f <= 0) {
                continue;
            }
            px($img, $x, $y, $color, $alpha * $fall * pow($f, 1.4));
        }
    }
}

/** Tia sáng toả từ một điểm (dùng cho đỉnh tháp vàng kim). */
function lightRays($img, float $cxF, float $cyF, int $count, float $lenF, array $color, float $alpha): void
{
    $cx = $cxF * W;
    $cy = $cyF * H;
    for ($i = 0; $i < $count; $i++) {
        $ang = ($i / $count) * M_PI * 2 + rndf(-0.06, 0.06);
        $len = $lenF * W * rndf(0.45, 1.0);
        softSegment($img, $cx, $cy, $cx + cos($ang) * $len, $cy + sin($ang) * $len,
            1.4 * SS, 10.0 * SS, $color, $alpha * rndf(0.35, 1.0), true);
    }
}

/** Khối mây bão: nhiều thùy tối chồng nhau, viền trên hắt sáng. */
function cloudMass($img, float $cyF, float $ampF, int $count, array $dark, array $rim, float $alpha, float $rimAlpha): void
{
    $cy = $cyF * H;
    $amp = $ampF * H;
    for ($i = 0; $i < $count; $i++) {
        $x = rndf(-0.12, 1.12) * W;
        $y = $cy + rndf(-1.0, 1.0) * $amp;
        $r = lerp(0.07, 0.26, pow(rnd(), 1.25)) * W;
        softDisc($img, $x, $y, $r, $dark, $alpha * (0.45 + 0.55 * rnd()), 0.60, 1.5);
        if (rnd() < 0.45) {
            softDisc($img, $x + $r * 0.10, $y - $r * 0.46, $r * 0.52, $rim, $rimAlpha * rndf(0.5, 1.0), 0.9, 1.9);
        }
    }
}

/** Tia sét gãy khúc + nhánh phụ. */
function lightningBolt($img, float $x0F, float $y0F, float $x1F, float $y1F, float $jitterF, array $glow, array $core, float $strength, int $forks = 2): void
{
    $x0 = $x0F * W;
    $y0 = $y0F * H;
    $x1 = $x1F * W;
    $y1 = $y1F * H;
    $jit = $jitterF * W;

    $steps = 13;
    $pts = [[$x0, $y0]];
    for ($i = 1; $i <= $steps; $i++) {
        $t = $i / $steps;
        $bx = lerp($x0, $x1, $t);
        $by = lerp($y0, $y1, $t);
        $off = $i === $steps ? 0.0 : rndf(-1.0, 1.0) * $jit * (0.30 + 0.85 * sin(M_PI * $t));
        $pts[] = [$bx + $off, $by];
    }

    for ($i = 0; $i < count($pts) - 1; $i++) {
        [$ax, $ay] = $pts[$i];
        [$bx, $by] = $pts[$i + 1];
        softSegment($img, $ax, $ay, $bx, $by, 4.0 * SS, 26.0 * SS, $glow, 0.085 * $strength);
        softSegment($img, $ax, $ay, $bx, $by, 1.8 * SS, 8.0 * SS, $glow, 0.20 * $strength);
        softSegment($img, $ax, $ay, $bx, $by, 0.9 * SS, 2.0 * SS, $core, 0.90 * $strength);
    }

    // quầng sáng dọc thân sét
    foreach ([0.25, 0.55, 0.85] as $s) {
        $idx = (int) ($s * (count($pts) - 1));
        softDisc($img, $pts[$idx][0], $pts[$idx][1], 0.16 * W, $glow, 0.10 * $strength, 1.0, 2.4);
    }

    if ($forks > 0) {
        for ($f = 0; $f < $forks; $f++) {
            $idx = (int) rndf(3, count($pts) - 3);
            [$fx, $fy] = $pts[$idx];
            $dir = rnd() < 0.5 ? -1 : 1;
            lightningBolt($img,
                $fx / W, $fy / H,
                ($fx + $dir * rndf(0.06, 0.16) * W) / W,
                ($fy + rndf(0.10, 0.22) * H) / H,
                $jitterF * 0.55, $glow, $core, $strength * 0.45, 0
            );
        }
    }
}

/** Mưa xiên. */
function rainStreaks($img, int $count, float $slant, array $color, float $alphaMax, float $lenMin, float $lenMax): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = rndf(-0.05, 1.05) * W;
        $y = rndf(-0.02, 1.0) * H;
        $len = rndf($lenMin, $lenMax) * H;
        softSegment($img, $x, $y, $x + $slant * $len, $y + $len, 0.45 * SS, 1.0 * SS, $color, $alphaMax * rndf(0.25, 1.0), true);
    }
}

/** Bokeh: vòng tròn mềm, xen kẽ đĩa đặc và vòng rỗng. */
function bokehField($img, int $count, array $palette, float $alphaMax, float $rMin, float $rMax): void
{
    for ($i = 0; $i < $count; $i++) {
        $cx = mt_rand(-40, W + 40);
        $cy = mt_rand(-40, H + 40);
        $r = lerp($rMin, $rMax, pow(rnd(), 1.7)) * SS;
        $c = $palette[mt_rand(0, count($palette) - 1)];
        $a = $alphaMax * (0.30 + 0.70 * rnd());

        if (mt_rand(0, 100) < 42) {
            softRing($img, (float) $cx, (float) $cy, $r, $r * 0.30, $c, $a * 0.95);
            softDisc($img, (float) $cx, (float) $cy, $r * 0.92, $c, $a * 0.22, 1.0, 1.4);
        } else {
            softDisc($img, (float) $cx, (float) $cy, $r, $c, $a * 0.75, 0.75, 1.5);
        }
    }
}

/* ────────────────────────────── Bóng người ────────────────────────────── */

/** Hợp các "viên nang" thon (x1,y1,r1 -> x2,y2,r2) thành một khối bóng liền mạch. */
function softBody($img, array $parts, array $color, float $alpha, float $feather = 1.15): void
{
    $f = $feather * SS;
    $minx = 1e9;
    $miny = 1e9;
    $maxx = -1e9;
    $maxy = -1e9;
    foreach ($parts as [$x1, $y1, $r1, $x2, $y2, $r2]) {
        $r = max($r1, $r2) + $f + 1;
        $minx = min($minx, $x1 - $r, $x2 - $r);
        $maxx = max($maxx, $x1 + $r, $x2 + $r);
        $miny = min($miny, $y1 - $r, $y2 - $r);
        $maxy = max($maxy, $y1 + $r, $y2 + $r);
    }
    $x0 = (int) max(0, floor($minx));
    $x1b = (int) min(W - 1, ceil($maxx));
    $y0 = (int) max(0, floor($miny));
    $y1b = (int) min(H - 1, ceil($maxy));

    for ($y = $y0; $y <= $y1b; $y++) {
        for ($x = $x0; $x <= $x1b; $x++) {
            $cov = 0.0;
            foreach ($parts as [$ax, $ay, $ar, $bx, $by, $br]) {
                $vx = $bx - $ax;
                $vy = $by - $ay;
                $len2 = $vx * $vx + $vy * $vy;
                $t = $len2 > 0.0001 ? clamp01((($x - $ax) * $vx + ($y - $ay) * $vy) / $len2) : 0.0;
                $r = $ar + ($br - $ar) * $t;
                $dx = $x - ($ax + $vx * $t);
                $dy = $y - ($ay + $vy * $t);
                $d = sqrt($dx * $dx + $dy * $dy);
                if ($d > $r + $f) {
                    continue;
                }
                $c = 1.0 - smoothstep($r - $f, $r + $f * 0.35, $d);
                if ($c > $cov) {
                    $cov = $c;
                }
                if ($cov >= 0.999) {
                    break;
                }
            }
            if ($cov > 0) {
                px($img, $x, $y, $color, $alpha * $cov);
            }
        }
    }
}

/**
 * Bộ "viên nang" tạo dáng người đứng.
 * $style: suit | coat | dress | worker  — $lean: nghiêng đầu/vai (-1..1) để hai người hướng vào nhau.
 */
function personParts(float $cx, float $feetY, float $h, string $style = 'suit', float $lean = 0.0): array
{
    $lx = $lean * 0.05 * $h;
    $parts = [
        // đầu
        [$cx + $lx, $feetY - 0.905 * $h, 0.047 * $h, $cx + $lx * 0.8, $feetY - 0.872 * $h, 0.050 * $h],
        // cổ
        [$cx + $lx * 0.6, $feetY - 0.848 * $h, 0.026 * $h, $cx, $feetY - 0.812 * $h, 0.031 * $h],
        // vai
        [$cx - 0.082 * $h, $feetY - 0.795 * $h, 0.034 * $h, $cx + 0.082 * $h, $feetY - 0.795 * $h, 0.034 * $h],
        // thân
        [$cx, $feetY - 0.795 * $h, 0.079 * $h, $cx, $feetY - 0.535 * $h, 0.064 * $h],
        // hông
        [$cx, $feetY - 0.560 * $h, 0.071 * $h, $cx, $feetY - 0.470 * $h, 0.065 * $h],
        // chân trái / phải
        [$cx - 0.032 * $h, $feetY - 0.480 * $h, 0.042 * $h, $cx - 0.040 * $h, $feetY - 0.004 * $h, 0.026 * $h],
        [$cx + 0.034 * $h, $feetY - 0.480 * $h, 0.042 * $h, $cx + 0.052 * $h, $feetY - 0.004 * $h, 0.026 * $h],
        // tay trái / phải
        [$cx - 0.084 * $h, $feetY - 0.775 * $h, 0.030 * $h, $cx - 0.100 * $h, $feetY - 0.500 * $h, 0.021 * $h],
        [$cx + 0.084 * $h, $feetY - 0.775 * $h, 0.030 * $h, $cx + 0.100 * $h, $feetY - 0.500 * $h, 0.021 * $h],
    ];

    if ($style === 'coat') {
        $parts[] = [$cx, $feetY - 0.640 * $h, 0.090 * $h, $cx, $feetY - 0.300 * $h, 0.082 * $h];
    } elseif ($style === 'dress') {
        $parts[] = [$cx, $feetY - 0.560 * $h, 0.072 * $h, $cx, $feetY - 0.150 * $h, 0.118 * $h];
        // tóc dài
        $parts[] = [$cx + $lx * 0.5, $feetY - 0.895 * $h, 0.054 * $h, $cx - 0.010 * $h, $feetY - 0.760 * $h, 0.040 * $h];
    } elseif ($style === 'worker') {
        // mũ lưỡi trai
        $parts[] = [$cx + $lx - 0.012 * $h, $feetY - 0.940 * $h, 0.040 * $h, $cx + $lx + 0.020 * $h, $feetY - 0.936 * $h, 0.036 * $h];
        $parts[] = [$cx + $lx + 0.030 * $h, $feetY - 0.930 * $h, 0.016 * $h, $cx + $lx + 0.075 * $h, $feetY - 0.924 * $h, 0.010 * $h];
        // áo khoác thợ, hơi rộng
        $parts[] = [$cx, $feetY - 0.700 * $h, 0.086 * $h, $cx, $feetY - 0.480 * $h, 0.078 * $h];
    }

    return $parts;
}

/** Vẽ bóng người kèm viền sáng (rim light) hắt từ nguồn sáng. */
function drawPerson($img, array $parts, array $color, float $alpha, ?array $rim = null, float $rimAlpha = 0.0, float $rimDx = 0.0, float $rimDy = 0.0, float $rimGrow = 2.2): void
{
    if ($rim !== null && $rimAlpha > 0) {
        $p2 = [];
        foreach ($parts as [$x1, $y1, $r1, $x2, $y2, $r2]) {
            $p2[] = [$x1 + $rimDx, $y1 + $rimDy, $r1 + $rimGrow * SS, $x2 + $rimDx, $y2 + $rimDy, $r2 + $rimGrow * SS];
        }
        softBody($img, $p2, $rim, $rimAlpha);
    }
    softBody($img, $parts, $color, $alpha);
}

/* ────────────────────────────── Dinh thự & cây ────────────────────────────── */

/** Cây thông/bách thẳng đứng — hàng cây hai bên lối vào. */
function cypress($img, float $xF, float $baseYF, float $hF, array $color, float $alpha): void
{
    $x = $xF * W;
    $by = $baseYF * H;
    $h = $hF * H;
    softEllipse($img, $x, $by - $h * 0.52, $h * 0.115, $h * 0.52, 0.0, $color, $alpha, 0.16, 1.0);
    softEllipse($img, $x, $by - $h * 0.80, $h * 0.075, $h * 0.24, 0.0, $color, $alpha * 0.9, 0.25, 1.0);
    softSegment($img, $x, $by, $x, $by - $h * 0.20, 2.0 * SS, 2.0 * SS, $color, $alpha);
}

/** Cây tán tròn được cắt tỉa. */
function roundTree($img, float $xF, float $baseYF, float $hF, array $color, float $alpha): void
{
    $x = $xF * W;
    $by = $baseYF * H;
    $h = $hF * H;
    softSegment($img, $x, $by, $x, $by - $h * 0.42, 2.6 * SS, 2.2 * SS, $color, $alpha);
    softDisc($img, $x, $by - $h * 0.66, $h * 0.30, $color, $alpha, 0.10, 1.0);
    softDisc($img, $x - $h * 0.20, $by - $h * 0.55, $h * 0.21, $color, $alpha, 0.14, 1.0);
    softDisc($img, $x + $h * 0.19, $by - $h * 0.57, $h * 0.20, $color, $alpha, 0.14, 1.0);
    softDisc($img, $x + $h * 0.03, $by - $h * 0.86, $h * 0.19, $color, $alpha, 0.16, 1.0);
}

/** Dinh thự: thân chính + hai cánh + mái dốc + hàng cột + cửa sổ sáng ấm. */
function drawMansion($img, array $c): void
{
    $cx = $c['x'] * W;
    $ground = $c['ground'] * H;
    $w = $c['w'] * W;
    $bodyH = $c['bodyH'] * H;
    $roofH = $c['roofH'] * H;
    $wallTop = $c['wallTop'];
    $wallBot = $c['wallBot'];
    $roofC = $c['roof'];
    $win = $c['win'];
    $winA = $c['winAlpha'];

    $l = $cx - $w / 2;
    $r = $cx + $w / 2;
    $bodyTop = $ground - $bodyH;

    // hai cánh nhà thấp hơn
    $wingW = $w * 0.30;
    $wingH = $bodyH * 0.66;
    foreach ([[$l - $wingW * 0.86, $l + $wingW * 0.14], [$r - $wingW * 0.14, $r + $wingW * 0.86]] as [$wl, $wr]) {
        $wt = $ground - $wingH;
        softPoly($img, [[$wl, $wt], [$wr, $wt], [$wr, $ground], [$wl, $ground]], $wallTop, $wallBot, 1.0, $wt, $ground);
        softPoly($img, [[$wl - $w * 0.012, $wt + 1], [$wr + $w * 0.012, $wt + 1], [$wr - $wingW * 0.20, $wt - $roofH * 0.62], [$wl + $wingW * 0.20, $wt - $roofH * 0.62]],
            $roofC, $roofC, 1.0, $wt - $roofH, $wt);
        windowGrid($img, $wl + $wingW * 0.16, $wt + $wingH * 0.20, $wr - $wingW * 0.16, $ground - $wingH * 0.14,
            5 * SS, 8 * SS, 9 * SS, 12 * SS, $win, $winA, 0.72, 0.55);
    }

    // thân chính
    softPoly($img, [[$l, $bodyTop], [$r, $bodyTop], [$r, $ground], [$l, $ground]], $wallTop, $wallBot, 1.0, $bodyTop, $ground);

    // mái dốc + diềm
    softPoly($img, [
        [$l - $w * 0.035, $bodyTop + 1], [$r + $w * 0.035, $bodyTop + 1],
        [$r - $w * 0.20, $bodyTop - $roofH], [$l + $w * 0.20, $bodyTop - $roofH],
    ], $roofC, $roofC, 1.0, $bodyTop - $roofH, $bodyTop);

    // ống khói
    foreach ([0.26, 0.74] as $f) {
        $chx = $l + $w * $f;
        $chw = $w * 0.035;
        softPoly($img, [
            [$chx - $chw, $bodyTop - $roofH * 1.42], [$chx + $chw, $bodyTop - $roofH * 1.42],
            [$chx + $chw, $bodyTop - $roofH * 0.45], [$chx - $chw, $bodyTop - $roofH * 0.45],
        ], $roofC, $roofC, 1.0);
    }

    // cửa sổ sáng ấm hai tầng
    windowGrid($img, $l + $w * 0.06, $bodyTop + $bodyH * 0.16, $r - $w * 0.06, $bodyTop + $bodyH * 0.40,
        6 * SS, 10 * SS, 11 * SS, 10 * SS, $win, $winA, 0.80, 0.60);
    windowGrid($img, $l + $w * 0.06, $bodyTop + $bodyH * 0.52, $l + $w * 0.30, $ground - $bodyH * 0.10,
        6 * SS, 11 * SS, 11 * SS, 10 * SS, $win, $winA, 0.75, 0.60);
    windowGrid($img, $r - $w * 0.30, $bodyTop + $bodyH * 0.52, $r - $w * 0.06, $ground - $bodyH * 0.10,
        6 * SS, 11 * SS, 11 * SS, 10 * SS, $win, $winA, 0.75, 0.60);

    // hiên cột giữa
    $pl = $cx - $w * 0.19;
    $pr = $cx + $w * 0.19;
    $pTop = $bodyTop + $bodyH * 0.30;
    softPoly($img, [[$pl - $w * 0.03, $pTop], [$pr + $w * 0.03, $pTop], [$pr, $pTop - $roofH * 0.40], [$pl, $pTop - $roofH * 0.40]],
        $roofC, $roofC, 1.0);
    $colW = $w * 0.022;
    for ($i = 0; $i < 4; $i++) {
        $x = $pl + ($pr - $pl) * ($i / 3);
        softPoly($img, [[$x - $colW, $pTop], [$x + $colW, $pTop], [$x + $colW, $ground], [$x - $colW, $ground]],
            $c['column'], $c['columnBot'] ?? $c['column'], 1.0, $pTop, $ground);
    }
    // cửa chính sáng
    softPoly($img, [
        [$cx - $w * 0.045, $ground - $bodyH * 0.34], [$cx + $w * 0.045, $ground - $bodyH * 0.34],
        [$cx + $w * 0.045, $ground], [$cx - $w * 0.045, $ground],
    ], $win, $win, $winA * 0.95);
    softDisc($img, $cx, $ground - $bodyH * 0.16, $w * 0.20, $win, $winA * 0.30, 1.0, 2.0);
}

/* ────────────────────────────── Các motif theo thể loại ────────────────────────────── */

/** billionaire / ceo — đường chân trời đêm, tháp kính, cửa sổ vàng kim, mặt nước phản chiếu. */
function motifSkyline($img, array $c): void
{
    gradientFill($img, $c['sky']);
    starField($img, $c['stars'] ?? 240, H * 0.44, $c['starColor'], 0.55);

    foreach ($c['glows'] as [$gx, $gy, $gr, $col, $ga]) {
        softDisc($img, $gx * W, $gy * H, $gr * W, $col, $ga, 1.0, 2.1);
    }

    foreach ($c['bands'] as $band) {
        skylineBand($img, $band);
        fogBand($img, $band['baseY'] * H - 5 * SS, 26 * SS, 9 * SS, $c['haze'], $c['hazeAlpha'] ?? 0.15);
    }

    foreach ($c['towers'] as $t) {
        heroTower($img, $t);
    }

    // khói đèn dưới chân phố
    fogBand($img, ($c['hazeY'] ?? 0.86) * H, 34 * SS, 10 * SS, $c['haze'], $c['hazeAlpha2'] ?? 0.18);

    if (! empty($c['water'])) {
        rectV($img, 0, $c['waterY'] * H, W, H, $c['waterTop'], $c['waterBot'], 1.0);
        waterReflection($img, $c['waterY'], $c['reflect'] ?? 0.42, $c['ripple'] ?? 2.4, $c['waterTint']);
        waterStreaks($img, $c['waterY'], $c['streaks'] ?? 26, $c['streakColor'], 0.30);
        softSegment($img, 0, $c['waterY'] * H, W, $c['waterY'] * H, 1.0 * SS, 4.0 * SS, $c['streakColor'], 0.18);
    }

    // sân thượng tiền cảnh + hai bóng người (biến thể lãng mạn)
    if (! empty($c['rooftop'])) {
        $ry = $c['roofY'] * H;
        rectV($img, 0, $ry, W, H, $c['roofTop'], $c['roofBot'], 1.0);
        softSegment($img, 0, $ry, W, $ry, 0.9 * SS, 3.2 * SS, $c['railing'], 0.55);
        for ($i = 0; $i <= 22; $i++) {
            $x = $i / 22 * W;
            softSegment($img, $x, $ry, $x, $ry + 0.030 * H, 0.8 * SS, 1.4 * SS, $c['railing'], 0.30);
        }
        softSegment($img, 0, $ry + 0.030 * H, W, $ry + 0.030 * H, 0.8 * SS, 2.2 * SS, $c['railing'], 0.28);

        foreach ($c['people'] as [$pxF, $hF, $style, $lean]) {
            $parts = personParts($pxF * W, $ry + 0.028 * H, $hF * H, $style, $lean);
            drawPerson($img, $parts, $c['figure'], 0.97, $c['rim'], 0.40, -2.0 * SS, -1.0 * SS, 1.8);
        }
    }
}

/** secret-identity — bóng người đơn độc trước thành phố, một vệt đèn rọi từ trên xuống. */
function motifSpotlight($img, array $c): void
{
    gradientFill($img, $c['sky']);
    starField($img, 150, H * 0.34, $c['starColor'], 0.42);

    softDisc($img, $c['glowX'] * W, $c['glowY'] * H, 0.85 * W, $c['glow'], 0.34, 1.0, 2.2);

    foreach ($c['bands'] as $band) {
        skylineBand($img, $band);
        fogBand($img, $band['baseY'] * H - 6 * SS, 30 * SS, 10 * SS, $c['haze'], 0.20);
    }

    // nền sàn / mặt đường
    rectV($img, 0, $c['floorY'] * H, W, H, $c['floorTop'], $c['floorBot'], 1.0);
    fogBand($img, $c['floorY'] * H, 22 * SS, 8 * SS, $c['haze'], 0.26);

    // nón đèn rọi
    lightBeam($img, $c['beamX'], -0.06, $c['beamL'], $c['beamR'], $c['beamBase'], $c['beam'], $c['beamAlpha'], 0.55);
    lightBeam($img, $c['beamX'], -0.06, lerp($c['beamX'], $c['beamL'], 0.45), lerp($c['beamX'], $c['beamR'], 0.45), $c['beamBase'], $c['beam'], $c['beamAlpha'] * 0.9, 0.7);

    // vũng sáng dưới chân
    softEllipse($img, $c['figX'] * W, $c['feetY'] * H + 0.006 * H, 0.20 * W, 0.045 * H, 0.0, $c['beam'], 0.34, 0.9, 1.6);

    // bóng đổ dài
    softEllipse($img, ($c['figX'] + 0.10) * W, ($c['feetY'] + 0.020) * H, 0.17 * W, 0.030 * H, 0.10, $c['shadow'], 0.55, 0.75, 1.3);

    // nhân vật
    $parts = personParts($c['figX'] * W, $c['feetY'] * H, $c['figH'] * H, $c['style'], $c['lean'] ?? 0.0);
    drawPerson($img, $parts, $c['figure'], 0.99, $c['rim'], 0.52, -2.4 * SS, -2.0 * SS, 2.4);

    // bụi sáng bay trong luồng đèn
    for ($i = 0; $i < 90; $i++) {
        $x = rndf($c['beamL'] - 0.05, $c['beamR'] + 0.05) * W;
        $y = rndf(0.05, $c['beamBase']) * H;
        softDisc($img, $x, $y, rndf(0.6, 1.7) * SS, $c['beam'], rndf(0.10, 0.42), 0.9, 1.3);
    }
}

/** romance — bokeh đèn thành phố ấm, hai bóng người trên tiền cảnh. */
function motifRomance($img, array $c): void
{
    gradientFill($img, $c['sky']);

    softDisc($img, $c['sunX'] * W, $c['sunY'] * H, 0.95 * W, $c['sunGlow'], 0.50, 1.0, 2.0);
    softDisc($img, $c['sunX'] * W, $c['sunY'] * H, 0.19 * W, $c['sunCore'], 0.55, 1.0, 1.8);

    foreach ($c['bands'] as $band) {
        skylineBand($img, $band);
    }
    fogBand($img, $c['hazeY'] * H, 46 * SS, 12 * SS, $c['haze'], 0.34);
    fogBand($img, ($c['hazeY'] + 0.03) * H, 26 * SS, 6 * SS, $c['haze'], 0.28);

    bokehField($img, $c['bokeh'], $c['bokehPalette'], 0.28, 12, $c['bokehR'] ?? 66);

    // tiền cảnh: lan can / mặt đường
    rectV($img, 0, $c['groundY'] * H, W, H, $c['groundTop'], $c['groundBot'], 1.0);
    fogBand($img, $c['groundY'] * H - 5 * SS, 13 * SS, 6 * SS, $c['haze'], 0.11);

    // đèn xe quét ngang mặt đường (biến thể "chiếc xe cũ")
    foreach ($c['streaks'] ?? [] as [$y, $x0, $x1, $col, $a, $core]) {
        softSegment($img, $x0 * W, $y * H, $x1 * W, $y * H, $core * SS, 9.0 * SS, $col, $a, true);
    }
    foreach ($c['lamps'] ?? [] as [$lx, $ly, $lr, $col, $a]) {
        softDisc($img, $lx * W, $ly * H, $lr * W, $col, $a, 1.0, 2.0);
        softDisc($img, $lx * W, $ly * H, $lr * W * 0.22, $col, min(1.0, $a * 2.2), 0.9, 1.4);
    }

    foreach ($c['people'] as [$pxF, $hF, $style, $lean]) {
        $parts = personParts($pxF * W, ($c['groundY'] + 0.012) * H, $hF * H, $style, $lean);
        drawPerson($img, $parts, $c['figure'], 0.98, $c['rim'], 0.46, $c['rimDx'] * SS, -2.0 * SS, 2.2);
    }

    // bokeh tiền cảnh (nhòe hơn, nằm đè lên nhân vật)
    bokehField($img, $c['bokehFront'], $c['bokehPalette'], 0.13, 40, 118);
    starField($img, 110, H * 0.96, $c['dust'], 0.40);
}

/** revenge — trời bão vần vũ trên thành phố, tia sét lạnh, mưa xiên. */
function motifStorm($img, array $c): void
{
    gradientFill($img, $c['sky']);

    // quầng bình minh / lửa hận phía chân trời
    foreach ($c['glows'] as [$gx, $gy, $gr, $col, $ga]) {
        softDisc($img, $gx * W, $gy * H, $gr * W, $col, $ga, 1.0, 2.2);
    }

    cloudMass($img, $c['cloudY'], $c['cloudAmp'], $c['clouds'], $c['cloudDark'], $c['cloudRim'], $c['cloudAlpha'], $c['cloudRimAlpha']);
    fogBand($img, $c['cloudY'] * H + 0.10 * H, 60 * SS, 30 * SS, $c['cloudDark'], 0.22);

    foreach ($c['bolts'] as [$x0, $y0, $x1, $y1, $jit, $st, $forks]) {
        lightningBolt($img, $x0, $y0, $x1, $y1, $jit, $c['boltGlow'], $c['boltCore'], $st, $forks);
    }

    foreach ($c['bands'] as $band) {
        skylineBand($img, $band);
        fogBand($img, $band['baseY'] * H - 6 * SS, 30 * SS, 10 * SS, $c['haze'], 0.16);
    }

    rainStreaks($img, $c['rain'], $c['rainSlant'], $c['rainColor'], $c['rainAlpha'], 0.030, 0.085);

    // ánh chớp hắt xuống mặt phố
    softDisc($img, $c['flashX'] * W, $c['flashY'] * H, 0.70 * W, $c['boltGlow'], 0.10, 1.0, 2.4);
    fogBand($img, H * 0.975, 46 * SS, 8 * SS, $c['haze'], 0.16);
}

/** family-drama — dinh thự với ô cửa sáng ấm, hàng cây, tông nâu ấm + kem. */
function motifMansion($img, array $c): void
{
    gradientFill($img, $c['sky']);
    starField($img, 120, H * 0.30, $c['starColor'], 0.35);

    softDisc($img, $c['glowX'] * W, $c['glowY'] * H, 0.90 * W, $c['glow'], 0.42, 1.0, 2.1);

    // rặng cây xa
    $far = makeRidge(H * $c['treeLineY'], H * 0.075, 13, true, 1.35);
    fillTerrain($img, $far, $c['farTop'], $c['farBot'], 0.85);
    fogBand($img, $c['treeLineY'] * H, 34 * SS, 14 * SS, $c['haze'], 0.28);

    // bãi cỏ
    rectV($img, 0, $c['ground'] * H, W, H, $c['lawnTop'], $c['lawnBot'], 1.0);

    drawMansion($img, $c['mansion']);

    // lối vào loe dần về phía người xem
    softPoly($img, [
        [($c['mansion']['x'] - 0.045) * W, $c['ground'] * H],
        [($c['mansion']['x'] + 0.045) * W, $c['ground'] * H],
        [($c['mansion']['x'] + 0.26) * W, H],
        [($c['mansion']['x'] - 0.26) * W, H],
    ], $c['driveTop'], $c['driveBot'], 0.95, $c['ground'] * H, H);

    // hàng cây hai bên
    foreach ($c['cypress'] as [$x, $y, $h]) {
        cypress($img, $x, $y, $h, $c['tree'], 0.95);
    }
    foreach ($c['trees'] as [$x, $y, $h]) {
        roundTree($img, $x, $y, $h, $c['tree'], 0.95);
    }

    // đèn lối đi
    foreach ($c['lamps'] as [$lx, $ly, $lr]) {
        softDisc($img, $lx * W, $ly * H, $lr * W, $c['lampGlow'], 0.55, 1.0, 1.9);
        softDisc($img, $lx * W, $ly * H, $lr * W * 0.20, $c['lampGlow'], 0.95, 0.9, 1.3);
    }

    fogBand($img, $c['ground'] * H + 0.02 * H, 40 * SS, 12 * SS, $c['haze'], 0.22);
    fogBand($img, H * 0.99, 50 * SS, 8 * SS, $c['haze'], 0.16);
}

/** rags-to-riches — xám xỉn dưới đáy, tháp vươn lên đỉnh vàng kim (tuỳ chọn hừng đông). */
function motifAscend($img, array $c): void
{
    gradientFill($img, $c['sky']);

    if (! empty($c['sun'])) {
        [$sx, $sy, $sr, $col, $core] = $c['sun'];
        softDisc($img, $sx * W, $sy * H, $sr * W, $col, 0.45, 1.0, 2.0);
        softDisc($img, $sx * W, $sy * H, $sr * W * 0.26, $core, 0.70, 0.9, 1.6);
    }

    starField($img, $c['stars'] ?? 90, H * 0.20, $c['starColor'], 0.35);

    // quầng vàng sau đỉnh tháp + tia sáng
    softDisc($img, $c['crownX'] * W, $c['crownY'] * H, 0.62 * W, $c['gold'], 0.34, 1.0, 2.2);
    lightRays($img, $c['crownX'], $c['crownY'], $c['rays'], 0.52, $c['gold'], 0.13);

    // phố xa (đồng thau) rồi phố gần (xám xỉn)
    foreach ($c['bands'] as $band) {
        skylineBand($img, $band);
        fogBand($img, $band['baseY'] * H - 5 * SS, 26 * SS, 12 * SS, $c['haze'], $band['haze'] ?? 0.12);
    }

    heroTower($img, $c['tower']);

    // đỉnh tháp rực sáng
    softDisc($img, $c['crownX'] * W, $c['crownY'] * H, 0.16 * W, $c['gold'], 0.38, 1.0, 1.9);
    softDisc($img, $c['crownX'] * W, $c['crownY'] * H, 0.05 * W, $c['goldCore'], 0.62, 1.0, 1.5);

    // sương xám bám dưới đáy -> nhấn tương phản nghèo / giàu
    fogBand($img, H * 0.905, 44 * SS, 16 * SS, $c['grime'], 0.14);
    fogBand($img, H * 0.972, 52 * SS, 10 * SS, $c['grime'], 0.18);

    // bụi vàng lấp lánh quanh đỉnh
    for ($i = 0; $i < 120; $i++) {
        $ang = rndf(0, 6.283);
        $dist = pow(rnd(), 0.7) * 0.30 * W;
        $x = $c['crownX'] * W + cos($ang) * $dist;
        $y = $c['crownY'] * H + sin($ang) * $dist * 0.9;
        softDisc($img, $x, $y, rndf(0.5, 1.8) * SS, $c['goldCore'], rndf(0.12, 0.55), 0.9, 1.3);
    }
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
 * 10 slug khớp với StorySeeder. Motif chọn theo thể loại chính;
 * truyện cùng thể loại đổi hẳn bố cục + bảng màu + hướng ánh sáng.
 */
$covers = [

    /* 1. The Janitor Owns the Company — secret-identity + ceo
       Bóng lao công đơn độc dưới vệt đèn rọi, thành phố tối phía sau. Xám lam + hổ phách. */
    'the-janitor-owns-the-company' => ['spotlight', [
        'sky' => [
            [0.00, hex('#05080F')],
            [0.22, hex('#0A1119')],
            [0.42, hex('#121D2A')],
            [0.60, hex('#1B2C3D')],
            [0.76, hex('#2A4055')],
            [1.00, hex('#3C5670')],
        ],
        'starColor' => hex('#D8E6F5'),
        'glow' => hex('#F0B45E'), 'glowX' => 0.44, 'glowY' => 0.72,
        'haze' => hex('#7E96B0'),
        'bands' => [
            ['baseY' => 0.775, 'minH' => 0.04, 'maxH' => 0.20, 'minW' => 0.030, 'maxW' => 0.070,
                'cTop' => hex('#1B2837'), 'cBot' => hex('#111C29'), 'alpha' => 0.62,
                'win' => hex('#E8A85A'), 'winAlpha' => 0.26, 'winChance' => 0.18, 'gap' => 0.006],
            ['baseY' => 0.815, 'minH' => 0.05, 'maxH' => 0.26, 'minW' => 0.045, 'maxW' => 0.095,
                'cTop' => hex('#0E1824'), 'cBot' => hex('#070E17'), 'alpha' => 0.92,
                'win' => hex('#F2B463'), 'winAlpha' => 0.42, 'winChance' => 0.22, 'gap' => 0.008],
        ],
        'floorY' => 0.815, 'floorTop' => hex('#101A26'), 'floorBot' => hex('#04070B'),
        'beam' => hex('#FFD79B'), 'beamAlpha' => 0.115,
        'beamX' => 0.435, 'beamL' => 0.215, 'beamR' => 0.665, 'beamBase' => 0.885,
        'figX' => 0.435, 'feetY' => 0.878, 'figH' => 0.315, 'style' => 'worker', 'lean' => -0.4,
        'figure' => hex('#04070C'), 'rim' => hex('#FFC978'),
        'shadow' => hex('#020407'),
    ]],

    /* 2. My Broke Husband Is a Billionaire — romance + billionaire
       Bokeh đèn thành phố ấm, hai bóng người quay vào nhau. Hồng đào + cam ấm. */
    'my-broke-husband-is-a-billionaire' => ['romance', [
        'sky' => [
            [0.00, hex('#2E1233')],
            [0.20, hex('#5C1F45')],
            [0.42, hex('#A03E5A')],
            [0.62, hex('#DC7160')],
            [0.80, hex('#F2A377')],
            [1.00, hex('#FFD6A6')],
        ],
        'sunX' => 0.70, 'sunY' => 0.34,
        'sunGlow' => hex('#FFC49B'), 'sunCore' => hex('#FFF1D9'),
        'bands' => [
            ['baseY' => 0.845, 'minH' => 0.05, 'maxH' => 0.22, 'minW' => 0.030, 'maxW' => 0.075,
                'cTop' => hex('#5B2B47'), 'cBot' => hex('#3A1A32'), 'alpha' => 0.50,
                'win' => hex('#FFD08A'), 'winAlpha' => 0.42, 'winChance' => 0.28, 'gap' => 0.007],
            ['baseY' => 0.885, 'minH' => 0.06, 'maxH' => 0.30, 'minW' => 0.045, 'maxW' => 0.100,
                'cTop' => hex('#3C1B31'), 'cBot' => hex('#210E1F'), 'alpha' => 0.80,
                'win' => hex('#FFDFA0'), 'winAlpha' => 0.58, 'winChance' => 0.34, 'gap' => 0.009],
        ],
        'hazeY' => 0.855, 'haze' => hex('#FFD1B0'),
        'bokeh' => 84, 'bokehFront' => 14, 'bokehR' => 68,
        'bokehPalette' => [hex('#FFD9E6'), hex('#FFB4A0'), hex('#FFE7C4'), hex('#FFFFFF'), hex('#F79FB8')],
        'groundY' => 0.918, 'groundTop' => hex('#25101F'), 'groundBot' => hex('#0C0410'),
        'people' => [[0.435, 0.215, 'suit', 0.55], [0.560, 0.200, 'dress', -0.55]],
        'figure' => hex('#160710'), 'rim' => hex('#FFC69A'), 'rimDx' => 2.4,
        'dust' => hex('#FFF3E4'),
    ]],

    /* 3. The Beggar at the Board Meeting — ceo + secret-identity
       Thành phố tài chính ban đêm, tháp kính vươn cao, mặt nước phản chiếu. Xanh đêm + vàng kim. */
    'the-beggar-at-the-board-meeting' => ['skyline', [
        'sky' => [
            [0.00, hex('#02060F')],
            [0.24, hex('#051129')],
            [0.44, hex('#091E45')],
            [0.62, hex('#0F2F5E')],
            [0.78, hex('#17457B')],
            [1.00, hex('#265E96')],
        ],
        'stars' => 300, 'starColor' => hex('#DCEBFF'),
        'glows' => [
            [0.28, 0.66, 0.80, hex('#2E77B8'), 0.42],
            [0.74, 0.72, 0.62, hex('#FFC46B'), 0.30],
        ],
        'haze' => hex('#5E8FC4'), 'hazeAlpha' => 0.14, 'hazeAlpha2' => 0.20, 'hazeY' => 0.845,
        'bands' => [
            ['baseY' => 0.755, 'minH' => 0.05, 'maxH' => 0.22, 'minW' => 0.028, 'maxW' => 0.062,
                'cTop' => hex('#123055'), 'cBot' => hex('#0A2140'), 'alpha' => 0.52,
                'win' => hex('#FFCE7E'), 'winAlpha' => 0.26, 'winChance' => 0.26, 'gap' => 0.006, 'winScale' => 0.85],
            ['baseY' => 0.812, 'minH' => 0.07, 'maxH' => 0.34, 'minW' => 0.040, 'maxW' => 0.082,
                'cTop' => hex('#0B2244'), 'cBot' => hex('#05142C'), 'alpha' => 0.80,
                'win' => hex('#FFD68A'), 'winAlpha' => 0.52, 'winChance' => 0.36, 'gap' => 0.008],
            ['baseY' => 0.868, 'minH' => 0.08, 'maxH' => 0.30, 'minW' => 0.055, 'maxW' => 0.110,
                'cTop' => hex('#061428'), 'cBot' => hex('#020814'), 'alpha' => 1.00,
                'win' => hex('#FFC96B'), 'winAlpha' => 0.68, 'winChance' => 0.30, 'gap' => 0.010, 'winScale' => 1.15],
        ],
        'towers' => [
            ['x' => 0.175, 'baseY' => 0.868, 'w' => 0.105, 'h' => 0.560, 'taper' => 0.68,
                'cTop' => hex('#123863'), 'cBot' => hex('#040C1C'), 'glass' => hex('#8FC4F0'), 'glassAlpha' => 0.13,
                'win' => hex('#FFCE79'), 'winAlpha' => 0.72, 'winChanceTop' => 0.52, 'winChanceBot' => 0.34,
                'edge' => hex('#BFE0FF'), 'edgeAlpha' => 0.30, 'litSide' => 1,
                'crown' => hex('#FFD98F'), 'crownAlpha' => 0.34, 'spire' => true, 'spireH' => 0.055, 'mullions' => 3],
            ['x' => 0.615, 'baseY' => 0.868, 'w' => 0.140, 'h' => 0.430, 'taper' => 0.86,
                'cTop' => hex('#0E2C52'), 'cBot' => hex('#030A18'), 'glass' => hex('#7FB4E4'), 'glassAlpha' => 0.11,
                'win' => hex('#FFD68A'), 'winAlpha' => 0.66, 'winChanceTop' => 0.46, 'winChanceBot' => 0.30,
                'edge' => hex('#A9D2F5'), 'edgeAlpha' => 0.26, 'litSide' => -1,
                'crown' => hex('#FFCE7E'), 'crownAlpha' => 0.28, 'spire' => false, 'mullions' => 4],
            ['x' => 0.845, 'baseY' => 0.868, 'w' => 0.095, 'h' => 0.330, 'taper' => 0.74,
                'cTop' => hex('#0C2748'), 'cBot' => hex('#020813'), 'glass' => hex('#7FB4E4'), 'glassAlpha' => 0.10,
                'win' => hex('#FFC96B'), 'winAlpha' => 0.60, 'winChanceTop' => 0.42, 'winChanceBot' => 0.26,
                'edge' => hex('#9CC8F0'), 'edgeAlpha' => 0.24, 'litSide' => 1,
                'crown' => hex('#FFD08A'), 'crownAlpha' => 0.26, 'spire' => true, 'spireH' => 0.035, 'mullions' => 3],
        ],
        'water' => true, 'waterY' => 0.872,
        'waterTop' => hex('#061428'), 'waterBot' => hex('#020610'),
        'waterTint' => hex('#071834'), 'reflect' => 0.40, 'ripple' => 2.6,
        'streaks' => 30, 'streakColor' => hex('#FFD08A'),
    ]],

    /* 4. Return of the Hidden Heir — revenge + billionaire
       Bão vần vũ trên thành phố, sét lạnh bên phải. Xanh đen + đỏ thẫm. */
    'return-of-the-hidden-heir' => ['storm', [
        'sky' => [
            [0.00, hex('#04060D')],
            [0.26, hex('#080D19')],
            [0.48, hex('#101828')],
            [0.66, hex('#1A2135')],
            [0.82, hex('#2C1E2C')],
            [1.00, hex('#4A1F24')],
        ],
        'glows' => [
            [0.55, 0.815, 0.66, hex('#8E1F24'), 0.40],
            [0.20, 0.86, 0.40, hex('#5A1A22'), 0.24],
        ],
        'cloudY' => 0.26, 'cloudAmp' => 0.13, 'clouds' => 34,
        'cloudDark' => hex('#080C16'), 'cloudRim' => hex('#4C5A78'),
        'cloudAlpha' => 0.34, 'cloudRimAlpha' => 0.16,
        'boltGlow' => hex('#A8CBFF'), 'boltCore' => hex('#FFFFFF'),
        'bolts' => [
            [0.655, -0.02, 0.545, 0.585, 0.055, 1.00, 3],
            [0.845, 0.02, 0.795, 0.330, 0.030, 0.34, 1],
        ],
        'flashX' => 0.60, 'flashY' => 0.62,
        'haze' => hex('#6B7794'),
        'bands' => [
            ['baseY' => 0.815, 'minH' => 0.05, 'maxH' => 0.22, 'minW' => 0.030, 'maxW' => 0.070,
                'cTop' => hex('#151C2C'), 'cBot' => hex('#0B1220'), 'alpha' => 0.66,
                'win' => hex('#E2764C'), 'winAlpha' => 0.24, 'winChance' => 0.16, 'gap' => 0.006],
            ['baseY' => 0.880, 'minH' => 0.07, 'maxH' => 0.30, 'minW' => 0.042, 'maxW' => 0.090,
                'cTop' => hex('#0C1120'), 'cBot' => hex('#050810'), 'alpha' => 0.90,
                'win' => hex('#F08A55'), 'winAlpha' => 0.40, 'winChance' => 0.20, 'gap' => 0.008],
            ['baseY' => 1.000, 'minH' => 0.09, 'maxH' => 0.26, 'minW' => 0.060, 'maxW' => 0.120,
                'cTop' => hex('#06090F'), 'cBot' => hex('#020305'), 'alpha' => 1.00,
                'win' => hex('#FF9E63'), 'winAlpha' => 0.52, 'winChance' => 0.14, 'gap' => 0.011, 'winScale' => 1.2],
        ],
        'rain' => 300, 'rainSlant' => -0.26, 'rainColor' => hex('#BBD2F0'), 'rainAlpha' => 0.20,
    ]],

    /* 5. She Laughed at His Old Car — romance + revenge
       Hoàng hôn mận đỏ, hai bóng người đứng cách xa, vệt đèn xe quét ngang. Hồng đào + đỏ mận. */
    'she-laughed-at-his-old-car' => ['romance', [
        'sky' => [
            [0.00, hex('#180B22')],
            [0.22, hex('#3A1230')],
            [0.44, hex('#6E1E3C')],
            [0.64, hex('#A93242')],
            [0.82, hex('#D9644B')],
            [1.00, hex('#F2A06C')],
        ],
        'sunX' => 0.26, 'sunY' => 0.60,
        'sunGlow' => hex('#FF9A70'), 'sunCore' => hex('#FFE0BC'),
        'bands' => [
            ['baseY' => 0.790, 'minH' => 0.04, 'maxH' => 0.19, 'minW' => 0.035, 'maxW' => 0.080,
                'cTop' => hex('#48203A'), 'cBot' => hex('#2A1128'), 'alpha' => 0.55,
                'win' => hex('#FFC178'), 'winAlpha' => 0.34, 'winChance' => 0.24, 'gap' => 0.008],
            ['baseY' => 0.842, 'minH' => 0.05, 'maxH' => 0.24, 'minW' => 0.050, 'maxW' => 0.105,
                'cTop' => hex('#2C1024'), 'cBot' => hex('#160616'), 'alpha' => 0.85,
                'win' => hex('#FFB765'), 'winAlpha' => 0.50, 'winChance' => 0.26, 'gap' => 0.010],
        ],
        'hazeY' => 0.812, 'haze' => hex('#FFB79A'),
        'bokeh' => 54, 'bokehFront' => 10, 'bokehR' => 52,
        'bokehPalette' => [hex('#FFC9C0'), hex('#F58B7E'), hex('#FFE0B8'), hex('#FFFFFF'), hex('#C2506A')],
        'streaks' => [
            [0.905, 0.02, 0.62, hex('#FFF0D0'), 0.55, 1.6],
            [0.928, 0.35, 0.99, hex('#FFC48A'), 0.45, 1.3],
            [0.952, 0.05, 0.80, hex('#FF8A6A'), 0.35, 1.1],
        ],
        'lamps' => [
            [0.615, 0.9045, 0.045, hex('#FFF3DA'), 0.55],
            [0.665, 0.9055, 0.038, hex('#FFF3DA'), 0.45],
            [0.345, 0.9285, 0.030, hex('#FFC48A'), 0.40],
        ],
        'groundY' => 0.884, 'groundTop' => hex('#1E0A1C'), 'groundBot' => hex('#080209'),
        'people' => [[0.300, 0.196, 'dress', 0.35], [0.700, 0.222, 'coat', -0.30]],
        'figure' => hex('#120510'), 'rim' => hex('#FF9E74'), 'rimDx' => -2.4,
        'dust' => hex('#FFE8DC'),
    ]],

    /* 6. Son-in-Law of the Silver Empire — family-drama + billionaire
       Dinh thự với ô cửa sáng ấm, hàng cây bách dọc lối vào. Nâu ấm + kem. */
    'son-in-law-of-the-silver-empire' => ['mansion', [
        'sky' => [
            [0.00, hex('#160F09')],
            [0.22, hex('#2C1D0F')],
            [0.44, hex('#4E3418')],
            [0.62, hex('#7A5024')],
            [0.78, hex('#A97438')],
            [0.92, hex('#D0A268')],
            [1.00, hex('#EBCB9C')],
        ],
        'starColor' => hex('#F4E4C8'),
        'glow' => hex('#E8B26A'), 'glowX' => 0.50, 'glowY' => 0.62,
        'haze' => hex('#E5C79A'),
        'treeLineY' => 0.660,
        'farTop' => hex('#2A1D12'), 'farBot' => hex('#1A1009'),
        'ground' => 0.760,
        'lawnTop' => hex('#241A10'), 'lawnBot' => hex('#0D0805'),
        'driveTop' => hex('#4A3A26'), 'driveBot' => hex('#8C7048'),
        'tree' => hex('#150E07'),
        'lampGlow' => hex('#FFD79A'),
        'mansion' => [
            'x' => 0.50, 'ground' => 0.760, 'w' => 0.400, 'bodyH' => 0.150, 'roofH' => 0.062,
            'wallTop' => hex('#3E2C1A'), 'wallBot' => hex('#241809'),
            'roof' => hex('#150D06'),
            'column' => hex('#6E5535'), 'columnBot' => hex('#4A3822'),
            'win' => hex('#FFD08A'), 'winAlpha' => 0.88,
        ],
        'cypress' => [
            [0.155, 0.800, 0.180], [0.255, 0.782, 0.150], [0.330, 0.770, 0.128],
            [0.845, 0.800, 0.185], [0.748, 0.783, 0.152], [0.672, 0.770, 0.126],
        ],
        'trees' => [
            [0.075, 0.845, 0.185], [0.925, 0.848, 0.190],
        ],
        'lamps' => [
            [0.395, 0.795, 0.030], [0.605, 0.795, 0.030],
            [0.320, 0.865, 0.036], [0.680, 0.865, 0.036],
        ],
    ]],

    /* 7. The Delivery Boy Who Bought the Mall — rags-to-riches + secret-identity
       Đáy xám xỉn, tháp giữa khung vươn lên đỉnh vàng kim rực rỡ. */
    'the-delivery-boy-who-bought-the-mall' => ['ascend', [
        'sky' => [
            [0.00, hex('#FFEFC0')],
            [0.14, hex('#F7CE72')],
            [0.30, hex('#C89A4C')],
            [0.48, hex('#8A7350')],
            [0.66, hex('#5A5648')],
            [0.82, hex('#3A3C42')],
            [1.00, hex('#1F2126')],
        ],
        'starColor' => hex('#FFF3D2'),
        'crownX' => 0.500, 'crownY' => 0.205, 'rays' => 16,
        'gold' => hex('#FFD98A'), 'goldCore' => hex('#FFF6DC'),
        'grime' => hex('#63656C'),
        'haze' => hex('#8A8474'),
        'bands' => [
            ['baseY' => 0.760, 'minH' => 0.05, 'maxH' => 0.17, 'minW' => 0.035, 'maxW' => 0.080,
                'cTop' => hex('#5E5140'), 'cBot' => hex('#3E3A2C'), 'alpha' => 0.62,
                'win' => hex('#E8C98A'), 'winAlpha' => 0.34, 'winChance' => 0.22, 'gap' => 0.008, 'haze' => 0.15],
            ['baseY' => 0.860, 'minH' => 0.05, 'maxH' => 0.16, 'minW' => 0.048, 'maxW' => 0.100,
                'cTop' => hex('#3A3C42'), 'cBot' => hex('#24262B'), 'alpha' => 0.90,
                'win' => hex('#B8B3A4'), 'winAlpha' => 0.26, 'winChance' => 0.16, 'gap' => 0.010, 'haze' => 0.16],
            ['baseY' => 1.000, 'minH' => 0.06, 'maxH' => 0.14, 'minW' => 0.065, 'maxW' => 0.135,
                'cTop' => hex('#212328'), 'cBot' => hex('#0E0F12'), 'alpha' => 1.00,
                'win' => hex('#9AA0A8'), 'winAlpha' => 0.24, 'winChance' => 0.12, 'gap' => 0.012, 'haze' => 0.12],
        ],
        'tower' => [
            'x' => 0.500, 'baseY' => 1.000, 'w' => 0.185, 'h' => 0.775, 'taper' => 0.66,
            'cTop' => hex('#8A6222'), 'cBot' => hex('#191B20'), 'glass' => hex('#FFE0A0'), 'glassAlpha' => 0.16,
            'win' => hex('#FFDF9C'), 'winAlpha' => 0.85, 'winChanceTop' => 0.78, 'winChanceBot' => 0.08,
            'edge' => hex('#FFEFC4'), 'edgeAlpha' => 0.46, 'litSide' => -1,
            'crown' => hex('#FFF0C0'), 'crownAlpha' => 0.40, 'spire' => true, 'spireH' => 0.045, 'mullions' => 4,
        ],
    ]],

    /* 8. Ten Years Poor, One Day King — rags-to-riches + second-chance
       Cũng là "xám dưới, vàng trên" nhưng là bình minh: tháp lệch phải, mặt trời mọc bên trái. */
    'ten-years-poor-one-day-king' => ['ascend', [
        'sky' => [
            [0.00, hex('#B7D6EE')],
            [0.16, hex('#E6C2A2')],
            [0.32, hex('#F7C88C')],
            [0.48, hex('#DCA672')],
            [0.64, hex('#9A8064')],
            [0.80, hex('#5E5A52')],
            [1.00, hex('#2B2D31')],
        ],
        'stars' => 40, 'starColor' => hex('#EAF3FF'),
        'sun' => [0.295, 0.375, 0.300, hex('#FFC08A'), hex('#FFF4DC')],
        'crownX' => 0.680, 'crownY' => 0.270, 'rays' => 12,
        'gold' => hex('#FFCE8A'), 'goldCore' => hex('#FFF3D6'),
        'grime' => hex('#6E727A'),
        'haze' => hex('#C6B49C'),
        'bands' => [
            ['baseY' => 0.735, 'minH' => 0.04, 'maxH' => 0.15, 'minW' => 0.030, 'maxW' => 0.070,
                'cTop' => hex('#7A6A56'), 'cBot' => hex('#544B3E'), 'alpha' => 0.52,
                'win' => hex('#FFE0AC'), 'winAlpha' => 0.26, 'winChance' => 0.18, 'gap' => 0.007, 'haze' => 0.18],
            ['baseY' => 0.845, 'minH' => 0.05, 'maxH' => 0.18, 'minW' => 0.045, 'maxW' => 0.095,
                'cTop' => hex('#413F3E'), 'cBot' => hex('#2A2926'), 'alpha' => 0.88,
                'win' => hex('#D8CBB0'), 'winAlpha' => 0.28, 'winChance' => 0.15, 'gap' => 0.009, 'haze' => 0.17],
            ['baseY' => 1.000, 'minH' => 0.05, 'maxH' => 0.13, 'minW' => 0.070, 'maxW' => 0.145,
                'cTop' => hex('#242629'), 'cBot' => hex('#101113'), 'alpha' => 1.00,
                'win' => hex('#A8A296'), 'winAlpha' => 0.22, 'winChance' => 0.11, 'gap' => 0.013, 'haze' => 0.13],
        ],
        'tower' => [
            'x' => 0.680, 'baseY' => 1.000, 'w' => 0.158, 'h' => 0.705, 'taper' => 0.70,
            'cTop' => hex('#7C5628'), 'cBot' => hex('#1E2024'), 'glass' => hex('#FFE9C0'), 'glassAlpha' => 0.15,
            'win' => hex('#FFD498'), 'winAlpha' => 0.80, 'winChanceTop' => 0.70, 'winChanceBot' => 0.10,
            'edge' => hex('#FFF2D2'), 'edgeAlpha' => 0.42, 'litSide' => -1,
            'crown' => hex('#FFEDC4'), 'crownAlpha' => 0.34, 'spire' => true, 'spireH' => 0.040, 'mullions' => 3,
        ],
    ]],

    /* 9. My Landlord Is a Secret CEO — romance + ceo
       Vẫn là skyline nhưng ấm màu mận–hổ phách, nhìn từ sân thượng có hai bóng người. */
    'my-landlord-is-a-secret-ceo' => ['skyline', [
        'sky' => [
            [0.00, hex('#0E0A20')],
            [0.24, hex('#221236')],
            [0.44, hex('#3E1B44')],
            [0.62, hex('#6B2F4E')],
            [0.78, hex('#A35059')],
            [0.90, hex('#CE7C63')],
            [1.00, hex('#EDA97E')],
        ],
        'stars' => 200, 'starColor' => hex('#FFE9F2'),
        'glows' => [
            [0.68, 0.640, 0.78, hex('#FFB870'), 0.44],
            [0.24, 0.700, 0.56, hex('#B0567E'), 0.26],
        ],
        'haze' => hex('#D89A86'), 'hazeAlpha' => 0.16, 'hazeAlpha2' => 0.22, 'hazeY' => 0.800,
        'bands' => [
            ['baseY' => 0.680, 'minH' => 0.05, 'maxH' => 0.24, 'minW' => 0.026, 'maxW' => 0.058,
                'cTop' => hex('#3E2340'), 'cBot' => hex('#2A1530'), 'alpha' => 0.50,
                'win' => hex('#FFC886'), 'winAlpha' => 0.30, 'winChance' => 0.30, 'gap' => 0.005, 'winScale' => 0.85],
            ['baseY' => 0.745, 'minH' => 0.07, 'maxH' => 0.36, 'minW' => 0.036, 'maxW' => 0.075,
                'cTop' => hex('#2C1730'), 'cBot' => hex('#180A1E'), 'alpha' => 0.80,
                'win' => hex('#FFD08A'), 'winAlpha' => 0.56, 'winChance' => 0.40, 'gap' => 0.007],
            ['baseY' => 0.815, 'minH' => 0.08, 'maxH' => 0.30, 'minW' => 0.050, 'maxW' => 0.100,
                'cTop' => hex('#180B1C'), 'cBot' => hex('#0A030C'), 'alpha' => 1.00,
                'win' => hex('#FFBE72'), 'winAlpha' => 0.70, 'winChance' => 0.34, 'gap' => 0.009, 'winScale' => 1.10],
        ],
        'towers' => [
            ['x' => 0.300, 'baseY' => 0.815, 'w' => 0.125, 'h' => 0.520, 'taper' => 0.72,
                'cTop' => hex('#4A2444'), 'cBot' => hex('#100610'), 'glass' => hex('#FFC9A0'), 'glassAlpha' => 0.13,
                'win' => hex('#FFD08A'), 'winAlpha' => 0.74, 'winChanceTop' => 0.56, 'winChanceBot' => 0.40,
                'edge' => hex('#FFE0B8'), 'edgeAlpha' => 0.32, 'litSide' => 1,
                'crown' => hex('#FFD9A0'), 'crownAlpha' => 0.36, 'spire' => true, 'spireH' => 0.050, 'mullions' => 3],
            ['x' => 0.760, 'baseY' => 0.815, 'w' => 0.115, 'h' => 0.380, 'taper' => 0.88,
                'cTop' => hex('#3A1C38'), 'cBot' => hex('#0C040C'), 'glass' => hex('#FFB894'), 'glassAlpha' => 0.11,
                'win' => hex('#FFC680'), 'winAlpha' => 0.64, 'winChanceTop' => 0.48, 'winChanceBot' => 0.32,
                'edge' => hex('#FFD2A8'), 'edgeAlpha' => 0.26, 'litSide' => -1,
                'crown' => hex('#FFCE8E'), 'crownAlpha' => 0.28, 'spire' => false, 'mullions' => 4],
        ],
        'rooftop' => true, 'roofY' => 0.870,
        'roofTop' => hex('#1A0C18'), 'roofBot' => hex('#070209'),
        'railing' => hex('#F0A878'),
        'people' => [[0.470, 0.150, 'suit', 0.5], [0.575, 0.140, 'dress', -0.5]],
        'figure' => hex('#0A0309'), 'rim' => hex('#FFB27A'),
    ]],

    /* 10. The Pauper's Revenge Empire — revenge + second-chance
        Bão đỏ thẫm, sét bên trái, phố dày đặc, vệt bình minh cam rạch ngang chân trời. */
    'the-paupers-revenge-empire' => ['storm', [
        'sky' => [
            [0.00, hex('#08040E')],
            [0.24, hex('#150612')],
            [0.46, hex('#2A0A17')],
            [0.64, hex('#48111C')],
            [0.80, hex('#742020')],
            [0.92, hex('#A8452C')],
            [1.00, hex('#E68A54')],
        ],
        'glows' => [
            [0.42, 0.885, 0.85, hex('#FF8A4E'), 0.27],
            [0.80, 0.900, 0.45, hex('#C24A26'), 0.24],
        ],
        'cloudY' => 0.215, 'cloudAmp' => 0.155, 'clouds' => 40,
        'cloudDark' => hex('#11060C'), 'cloudRim' => hex('#7A3030'),
        'cloudAlpha' => 0.32, 'cloudRimAlpha' => 0.20,
        'boltGlow' => hex('#FFC6A0'), 'boltCore' => hex('#FFF6EC'),
        'bolts' => [
            [0.285, -0.02, 0.375, 0.545, 0.060, 0.95, 3],
            [0.115, 0.00, 0.155, 0.285, 0.028, 0.30, 1],
        ],
        'flashX' => 0.34, 'flashY' => 0.60,
        'haze' => hex('#8A5A50'),
        'bands' => [
            ['baseY' => 0.770, 'minH' => 0.06, 'maxH' => 0.26, 'minW' => 0.025, 'maxW' => 0.058,
                'cTop' => hex('#22111A'), 'cBot' => hex('#150A11'), 'alpha' => 0.62,
                'win' => hex('#FF9E5E'), 'winAlpha' => 0.26, 'winChance' => 0.20, 'gap' => 0.005, 'winScale' => 0.85],
            ['baseY' => 0.845, 'minH' => 0.08, 'maxH' => 0.34, 'minW' => 0.036, 'maxW' => 0.078,
                'cTop' => hex('#160A11'), 'cBot' => hex('#0A0409'), 'alpha' => 0.90,
                'win' => hex('#FF8A46'), 'winAlpha' => 0.44, 'winChance' => 0.22, 'gap' => 0.007],
            ['baseY' => 1.000, 'minH' => 0.10, 'maxH' => 0.30, 'minW' => 0.052, 'maxW' => 0.108,
                'cTop' => hex('#0B0409'), 'cBot' => hex('#030103'), 'alpha' => 1.00,
                'win' => hex('#FF7A3C'), 'winAlpha' => 0.56, 'winChance' => 0.16, 'gap' => 0.010, 'winScale' => 1.15],
        ],
        'rain' => 190, 'rainSlant' => 0.22, 'rainColor' => hex('#FFC7A8'), 'rainAlpha' => 0.16,
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
        'skyline' => motifSkyline($img, $cfg),
        'spotlight' => motifSpotlight($img, $cfg),
        'romance' => motifRomance($img, $cfg),
        'storm' => motifStorm($img, $cfg),
        'mansion' => motifMansion($img, $cfg),
        'ascend' => motifAscend($img, $cfg),
    };

    // Thu nhỏ về 600x800 -> khử răng cưa toàn ảnh
    $out = imagecreatetruecolor(OUT_W, OUT_H);
    imagealphablending($out, true);
    imagecopyresampled($out, $img, 0, 0, 0, 0, OUT_W, OUT_H, W, H);
    imagedestroy($img);

    vignette($out, OUT_W, OUT_H, $motif === 'romance' ? 0.24 : 0.34, 0.50);
    grain($out, OUT_W, OUT_H, $motif === 'storm' ? 4 : 3);

    $path = $outDir.'/'.$slug.'.jpg';
    imagejpeg($out, $path, 90);
    imagedestroy($out);

    printf("  ✓ %-38s %-9s (%d KB)\n", $slug, $motif, (int) round(filesize($path) / 1024));
}

printf("Xong: %d artwork (600x800, JPEG q90) tại %s — %.1fs\n", count($covers), $outDir, microtime(true) - $t0);

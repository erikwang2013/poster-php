<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Qrcode;

use GdImage;
use InvalidArgumentException;

/**
 * QR Code Model 2 generator (ISO/IEC 18004), versions 1-40 x L/M/Q/H.
 *
 * Byte mode encoding, GF(256) Reed-Solomon error correction (primitive
 * polynomial 0x11D) in the standard's block structure, remainder bits, and the
 * mask pattern with the lowest penalty score. Format information BCH(15,5) and
 * version information BCH(18,6) are generated rather than tabulated.
 */
class QrcodeGenerator
{
    private string $text = '';
    private int $size = 200;
    private int $margin = 2;
    private string $errorLevel = 'H';
    private int $foreground = 0x000000;
    private int $background = 0xFFFFFF;

    private const LEVELS = ['L', 'M', 'Q', 'H'];

    /** Error correction level indicator bits carried inside the format information. */
    private const LEVEL_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    // Alignment pattern centre coordinates per version (Annex E)
    private const ALIGNMENTS = [
        1  => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6  => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62],
        14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70], 16 => [6, 26, 50, 74],
        17 => [6, 30, 54, 78], 18 => [6, 30, 56, 82], 19 => [6, 30, 58, 86],
        20 => [6, 34, 62, 90], 21 => [6, 28, 50, 72, 94], 22 => [6, 26, 50, 74, 98],
        23 => [6, 30, 54, 78, 102], 24 => [6, 28, 54, 80, 106], 25 => [6, 32, 58, 84, 110],
        26 => [6, 30, 58, 86, 114], 27 => [6, 34, 62, 90, 118], 28 => [6, 26, 50, 74, 98, 122],
        29 => [6, 30, 54, 78, 102, 126], 30 => [6, 26, 52, 78, 104, 130],
        31 => [6, 30, 56, 82, 108, 134], 32 => [6, 34, 60, 86, 112, 138],
        33 => [6, 30, 58, 86, 114, 142], 34 => [6, 34, 62, 90, 118, 146],
        35 => [6, 30, 54, 78, 102, 126, 150], 36 => [6, 24, 50, 76, 102, 128, 154],
        37 => [6, 28, 54, 80, 106, 132, 158], 38 => [6, 32, 58, 84, 110, 136, 162],
        39 => [6, 26, 54, 82, 110, 138, 166], 40 => [6, 30, 58, 86, 114, 142, 170],
    ];

    // Error correction codewords per block (Annex I, Table 9). Index = version.
    private const ECC_PER_BLOCK = [
        'L' => [
            0,
            7, 10, 15, 20, 26, 18, 20, 24, 30, 18,
            20, 24, 26, 30, 22, 24, 28, 30, 28, 28,
            28, 28, 30, 30, 26, 28, 30, 30, 30, 30,
            30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
        ],
        'M' => [
            0,
            10, 16, 26, 18, 24, 16, 18, 22, 22, 26,
            30, 22, 22, 24, 24, 28, 28, 26, 26, 26,
            26, 28, 28, 28, 28, 28, 28, 28, 28, 28,
            28, 28, 28, 28, 28, 28, 28, 28, 28, 28,
        ],
        'Q' => [
            0,
            13, 22, 18, 26, 18, 24, 18, 22, 20, 24,
            28, 26, 24, 20, 30, 24, 28, 28, 26, 30,
            28, 30, 30, 30, 30, 28, 30, 30, 30, 30,
            30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
        ],
        'H' => [
            0,
            17, 28, 22, 16, 22, 28, 26, 26, 24, 28,
            24, 28, 22, 24, 24, 30, 28, 28, 26, 28,
            30, 24, 30, 30, 30, 30, 30, 30, 30, 30,
            30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
        ],
    ];

    // Number of error correction blocks (Annex I, Table 9). Index = version.
    private const EC_BLOCK_COUNT = [
        'L' => [
            0,
            1, 1, 1, 1, 1, 2, 2, 2, 2, 4,
            4, 4, 4, 4, 6, 6, 6, 6, 7, 8,
            8, 9, 9, 10, 12, 12, 12, 13, 14, 15,
            16, 17, 18, 19, 19, 20, 21, 22, 24, 25,
        ],
        'M' => [
            0,
            1, 1, 1, 2, 2, 4, 4, 4, 5, 5,
            5, 8, 9, 9, 10, 10, 11, 13, 14, 16,
            17, 17, 18, 20, 21, 23, 25, 26, 28, 29,
            31, 33, 35, 37, 38, 40, 43, 45, 47, 49,
        ],
        'Q' => [
            0,
            1, 1, 2, 2, 4, 4, 6, 6, 8, 8,
            8, 10, 12, 16, 12, 17, 16, 18, 21, 20,
            23, 23, 25, 27, 29, 34, 34, 35, 38, 40,
            43, 45, 48, 51, 53, 56, 59, 62, 65, 68,
        ],
        'H' => [
            0,
            1, 1, 2, 4, 4, 4, 5, 6, 8, 8,
            11, 11, 16, 16, 18, 16, 19, 21, 25, 25,
            25, 34, 30, 32, 35, 37, 40, 42, 45, 48,
            51, 54, 57, 60, 63, 66, 70, 74, 77, 81,
        ],
    ];

    // Coordinates of the encoding region modules; the mask applies only to these,
    // never to function patterns.
    private array $dataCells = [];

    private static array $gfExp = [];
    private static array $gfLog = [];
    private static array $rsDivisor = [];

    // --- Public API ---
    public function setText(string $text): static { $this->text = $text; return $this; }
    public function setSize(int $size): static { $this->size = max(21, $size); return $this; }
    public function setMargin(int $margin): static { $this->margin = max(0, $margin); return $this; }
    public function setErrorLevel(string $level): static { $this->errorLevel = strtoupper($level); return $this; }
    public function setForeground(int $rgb): static { $this->foreground = $rgb; return $this; }
    public function setBackground(int $rgb): static { $this->background = $rgb; return $this; }

    public function render(): GdImage
    {
        if ($this->text === '') {
            throw new InvalidArgumentException('QR code text cannot be empty');
        }

        $levelIndex = array_search($this->errorLevel, self::LEVELS, true);
        if ($levelIndex === false) {
            $levelIndex = 3;
        }

        $bytes = array_values(unpack('C*', $this->text));
        $version = $this->findVersion(count($bytes), $levelIndex);
        $n = $version * 4 + 17;

        $codewords = $this->buildCodewords($bytes, $version, $levelIndex);
        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        // Remainder bits: the encoding region is not always a whole number of codewords.
        $bits .= str_repeat('0', self::rawDataModules($version) % 8);

        $modules = array_fill(0, $n, array_fill(0, $n, null));
        $this->placeFinders($modules, $n);
        $this->placeTiming($modules, $n);
        $this->placeAlignments($modules, $version, $n);
        $this->placeVersion($modules, $version, $n);
        $this->reserveFormat($modules, $n);
        $modules[$n - 8][8] = true; // dark module
        $this->placeData($modules, $bits, $n);

        $bestMask = $this->bestMask($modules, $n, $levelIndex);
        $this->applyMask($modules, $bestMask);
        $this->placeFormat($modules, $levelIndex, $bestMask, $n);

        return $this->renderImage($modules, $n);
    }

    // --- Version / capacity ---
    /** Smallest version whose data capacity holds the byte-mode segment, or throw. */
    private function findVersion(int $byteCount, int $levelIndex): int
    {
        for ($version = 1; $version <= 40; $version++) {
            $dataCodewords = array_sum($this->blockLayout($version, $levelIndex));
            $countBits = $version <= 9 ? 8 : 16;
            if ($dataCodewords * 8 >= 4 + $countBits + $byteCount * 8) {
                return $version;
            }
        }
        throw new InvalidArgumentException('Data too large for QR code');
    }

    /** Data codeword count of every block of a symbol (short blocks first), per the standard. */
    private function blockLayout(int $version, int $levelIndex): array
    {
        $level = self::LEVELS[$levelIndex];
        if ($version < 1 || $version > 40 || !isset(self::ECC_PER_BLOCK[$level][$version])) {
            throw new InvalidArgumentException('Unsupported QR code version: ' . $version);
        }

        $eccPerBlock = self::ECC_PER_BLOCK[$level][$version];
        $blockCount = self::EC_BLOCK_COUNT[$level][$version];
        $rawCodewords = intdiv(self::rawDataModules($version), 8);

        $shortDataLength = intdiv($rawCodewords, $blockCount) - $eccPerBlock;
        $shortBlocks = $blockCount - $rawCodewords % $blockCount;

        $layout = array_fill(0, $shortBlocks, $shortDataLength);
        for ($i = $shortBlocks; $i < $blockCount; $i++) {
            $layout[] = $shortDataLength + 1;
        }
        return $layout;
    }

    /**
     * Number of modules available to the encoding region (data + error correction
     * codewords + remainder bits) for a version, derived from its geometry.
     */
    private static function rawDataModules(int $version): int
    {
        if ($version < 1 || $version > 40) {
            throw new InvalidArgumentException('Unsupported QR code version: ' . $version);
        }

        $modules = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $align = intdiv($version, 7) + 2;
            $modules -= (25 * $align - 10) * $align - 55;
        }
        if ($version >= 7) {
            $modules -= 36; // version information blocks
        }
        return $modules;
    }

    // --- Encoding ---
    /** Interleaved data + error correction codewords of a complete symbol. */
    private function buildCodewords(array $bytes, int $version, int $levelIndex): array
    {
        $layout = $this->blockLayout($version, $levelIndex);
        $eccPerBlock = self::ECC_PER_BLOCK[self::LEVELS[$levelIndex]][$version];
        $capacity = array_sum($layout);

        $bits = '0100'; // byte mode
        $bits .= str_pad(decbin(count($bytes)), $version <= 9 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        // Terminator, then bit padding to the codeword boundary.
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        $data = [];
        for ($i = 0, $len = strlen($bits); $i < $len; $i += 8) {
            $data[] = bindec(substr($bits, $i, 8));
        }
        $pad = [0xEC, 0x11];
        for ($i = 0; count($data) < $capacity; $i ^= 1) {
            $data[] = $pad[$i];
        }

        $blocks = [];
        $offset = 0;
        foreach ($layout as $dataLength) {
            $block = array_slice($data, $offset, $dataLength);
            $offset += $dataLength;
            $blocks[] = [$block, $this->rsRemainder($block, $eccPerBlock)];
        }

        // Interleave: i-th data codeword of every block, then i-th ECC codeword of every block.
        $result = [];
        for ($i = 0; $i < max($layout); $i++) {
            foreach ($blocks as [$block, $ecc]) {
                if ($i < count($block)) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $eccPerBlock; $i++) {
            foreach ($blocks as [$block, $ecc]) {
                $result[] = $ecc[$i];
            }
        }
        return $result;
    }

    // --- GF(256) Reed-Solomon ---
    private static function initGf(): void
    {
        if (self::$gfExp !== []) {
            return;
        }
        $exp = [];
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $value <<= 1;
            if ($value & 0x100) {
                $value ^= 0x11D; // primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
            }
        }
        $log = array_fill(0, 256, 0);
        foreach ($exp as $i => $value) {
            $log[$value] = $i;
        }
        self::$gfExp = $exp;
        self::$gfLog = $log;
    }

    private static function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$gfExp[(self::$gfLog[$a] + self::$gfLog[$b]) % 255];
    }

    /** Generator polynomial coefficients (x^e excluded) for an ECC block of the given size. */
    private static function rsDivisor(int $degree): array
    {
        self::initGf();
        if (isset(self::$rsDivisor[$degree])) {
            return self::$rsDivisor[$degree];
        }

        $divisor = array_fill(0, $degree - 1, 0);
        $divisor[] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $divisor[$j] = self::gfMultiply($divisor[$j], $root);
                if ($j + 1 < $degree) {
                    $divisor[$j] ^= $divisor[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 2);
        }
        return self::$rsDivisor[$degree] = $divisor;
    }

    /** Error correction codewords (remainder of the data polynomial divided by the generator). */
    private function rsRemainder(array $data, int $degree): array
    {
        $divisor = self::rsDivisor($degree);
        $remainder = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            for ($i = 0; $i < $degree - 1; $i++) {
                $remainder[$i] = $remainder[$i + 1] ^ self::gfMultiply($divisor[$i], $factor);
            }
            $remainder[$degree - 1] = self::gfMultiply($divisor[$degree - 1], $factor);
        }
        return $remainder;
    }

    // --- Module placement ---
    private function placeFinders(array &$m, int $n): void
    {
        foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$row, $col]) {
            for ($i = -1; $i <= 7; $i++) {
                for ($j = -1; $j <= 7; $j++) {
                    $r = $row + $i;
                    $c = $col + $j;
                    if ($r < 0 || $r >= $n || $c < 0 || $c >= $n) {
                        continue;
                    }
                    $inFinder = $i >= 0 && $i <= 6 && $j >= 0 && $j <= 6;
                    $m[$r][$c] = $inFinder && (
                        $i === 0 || $i === 6 || $j === 0 || $j === 6 || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)
                    );
                }
            }
        }
    }

    private function placeTiming(array &$m, int $n): void
    {
        for ($i = 8; $i < $n - 8; $i++) {
            if ($m[$i][6] === null) { $m[$i][6] = $i % 2 === 0; }
            if ($m[6][$i] === null) { $m[6][$i] = $i % 2 === 0; }
        }
    }

    private function placeAlignments(array &$m, int $version, int $n): void
    {
        $positions = self::ALIGNMENTS[$version];
        foreach ($positions as $row) {
            foreach ($positions as $col) {
                // Skip the three centres that would collide with a finder pattern.
                if (($row < 9 && $col < 9) || ($row < 9 && $col > $n - 10) || ($row > $n - 10 && $col < 9)) {
                    continue;
                }
                for ($i = -2; $i <= 2; $i++) {
                    for ($j = -2; $j <= 2; $j++) {
                        $m[$row + $i][$col + $j] = abs($i) === 2 || abs($j) === 2 || ($i === 0 && $j === 0);
                    }
                }
            }
        }
    }

    /** Reserve (as non-data) the modules that will carry the format information. */
    private function reserveFormat(array &$m, int $n): void
    {
        for ($i = 0; $i <= 8; $i++) {
            if ($m[$i][8] === null) { $m[$i][8] = false; }
            if ($m[8][$i] === null) { $m[8][$i] = false; }
        }
        for ($i = $n - 8; $i < $n; $i++) {
            if ($m[8][$i] === null) { $m[8][$i] = false; }
        }
        for ($i = $n - 7; $i < $n; $i++) {
            if ($m[$i][8] === null) { $m[$i][8] = false; }
        }
    }

    /** Two-module wide zigzag from the bottom-right corner, skipping the timing column. */
    private function placeData(array &$m, string $bits, int $n): void
    {
        $this->dataCells = [];
        $bitsLength = strlen($bits);
        $index = 0;
        $upward = true;

        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col = 5; // column 6 is the vertical timing pattern
            }
            for ($i = 0; $i < $n; $i++) {
                $row = $upward ? $n - 1 - $i : $i;
                foreach ([$col, $col - 1] as $c) {
                    if ($m[$row][$c] !== null) {
                        continue;
                    }
                    $m[$row][$c] = $index < $bitsLength && $bits[$index] === '1';
                    $this->dataCells[] = [$row, $c];
                    $index++;
                }
            }
            $upward = !$upward;
        }
    }

    // --- Masks ---
    // Masking applies to the encoding region only: finder, timing, alignment and
    // format/version modules must never be inverted.
    private function applyMask(array &$m, int $mask): void
    {
        foreach ($this->dataCells as [$r, $c]) {
            $invert = match ($mask) {
                0 => ($r + $c) % 2 === 0,
                1 => $r % 2 === 0,
                2 => $c % 3 === 0,
                3 => ($r + $c) % 3 === 0,
                4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
                6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
                7 => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
                default => false,
            };
            if ($invert) {
                $m[$r][$c] = !$m[$r][$c];
            }
        }
    }

    /**
     * Mask with the lowest penalty, scored on the complete symbol (format bits included).
     * 8 full-symbol evaluations: ~1s for a v40 symbol, negligible for the usual v2-v10 poster codes.
     */
    private function bestMask(array $modules, int $n, int $levelIndex): int
    {
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $test = $modules;
            $this->applyMask($test, $mask);
            $this->placeFormat($test, $levelIndex, $mask, $n);
            $score = $this->penalty($test, $n);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
            }
        }
        return $bestMask;
    }

    private function penalty(array $m, int $n): int
    {
        $penalty = 0;

        // N1: runs of five or more modules of the same colour.
        for ($r = 0; $r < $n; $r++) {
            $run = 1;
            for ($c = 1; $c < $n; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) { $penalty += 3 + $run - 5; }
                    $run = 1;
                }
            }
            if ($run >= 5) { $penalty += 3 + $run - 5; }
        }
        for ($c = 0; $c < $n; $c++) {
            $run = 1;
            for ($r = 1; $r < $n; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) {
                    $run++;
                } else {
                    if ($run >= 5) { $penalty += 3 + $run - 5; }
                    $run = 1;
                }
            }
            if ($run >= 5) { $penalty += 3 + $run - 5; }
        }

        // N2: 2x2 blocks of the same colour.
        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                if ($m[$r][$c] === $m[$r][$c + 1] && $m[$r][$c] === $m[$r + 1][$c] && $m[$r][$c] === $m[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }

        // N3: 1:1:3:1:1 finder-like patterns with four light modules on one side,
        // in both orientations of the pattern.
        for ($r = 0; $r < $n; $r++) {
            $line = '';
            for ($c = 0; $c < $n; $c++) { $line .= $m[$r][$c] ? '1' : '0'; }
            $penalty += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
        }
        for ($c = 0; $c < $n; $c++) {
            $line = '';
            for ($r = 0; $r < $n; $r++) { $line .= $m[$r][$c] ? '1' : '0'; }
            $penalty += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
        }

        // N4: deviation of the dark module ratio from 50%, in 5% steps.
        $dark = 0;
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c]) { $dark++; }
            }
        }
        $penalty += 10 * intdiv(abs($dark * 100 - $n * $n * 50), $n * $n * 5);

        return $penalty;
    }

    // --- Format & version information ---
    private function placeFormat(array &$m, int $levelIndex, int $mask, int $n): void
    {
        $bits = self::formatInfo(self::LEVEL_BITS[self::LEVELS[$levelIndex]], $mask);
        $bit = static function (int $i) use ($bits): bool {
            return (($bits >> $i) & 1) !== 0;
        };

        // Copy 1: around the top-left finder pattern (column 8 rows 0-5 and 7-8, row 8).
        for ($i = 0; $i < 6; $i++) { $m[$i][8] = $bit($i); }
        $m[7][8] = $bit(6);
        $m[8][8] = $bit(7);
        $m[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) { $m[8][14 - $i] = $bit($i); }

        // Copy 2: top-right (row 8) and bottom-left (column 8).
        for ($i = 0; $i < 8; $i++) { $m[8][$n - 1 - $i] = $bit($i); }
        for ($i = 8; $i < 15; $i++) { $m[$n - 15 + $i][8] = $bit($i); }
    }

    private function placeVersion(array &$m, int $version, int $n): void
    {
        if ($version < 7) {
            return;
        }
        $bits = self::versionInfo($version);
        for ($i = 0; $i < 18; $i++) {
            $value = (($bits >> $i) & 1) !== 0;
            $m[$n - 11 + $i % 3][intdiv($i, 3)] = $value;
            $m[intdiv($i, 3)][$n - 11 + $i % 3] = $value;
        }
    }

    /** BCH(15,5) format information, XOR-masked with 0x5412. */
    private static function formatInfo(int $levelBits, int $mask): int
    {
        $data = ($levelBits << 3) | $mask;
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }
        return (($data << 10) | $remainder) ^ 0x5412;
    }

    /** BCH(18,6) version information. */
    private static function versionInfo(int $version): int
    {
        $remainder = $version;
        for ($i = 0; $i < 12; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
        }
        return ($version << 12) | $remainder;
    }

    // --- Render ---
    private function renderImage(array $modules, int $moduleCount): GdImage
    {
        $totalCount = $moduleCount + $this->margin * 2;
        $scale = max(1, intval($this->size / $totalCount));
        $imgSize = $totalCount * $scale;

        $img = imagecreatetruecolor($imgSize, $imgSize);
        $fg = imagecolorallocate($img, ($this->foreground >> 16) & 0xFF, ($this->foreground >> 8) & 0xFF, $this->foreground & 0xFF);
        $bg = imagecolorallocate($img, ($this->background >> 16) & 0xFF, ($this->background >> 8) & 0xFF, $this->background & 0xFF);
        imagefill($img, 0, 0, $bg);

        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if (!empty($modules[$r][$c])) {
                    imagefilledrectangle(
                        $img,
                        ($c + $this->margin) * $scale,
                        ($r + $this->margin) * $scale,
                        ($c + $this->margin + 1) * $scale - 1,
                        ($r + $this->margin + 1) * $scale - 1,
                        $fg
                    );
                }
            }
        }

        return $img;
    }
}

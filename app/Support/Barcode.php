<?php

namespace App\Support;

/** Minimal Code 39 barcode renderer (SVG) for VIN / SKU labels. */
final class Barcode
{
    private const PATTERNS = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
        '$' => 'nwnwnwnnn', '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn',
    ];

    public static function code39Svg(string $text, int $height = 50, float $narrow = 1.4): string
    {
        $text = '*'.strtoupper(preg_replace('/[^0-9A-Za-z\-\. \$\/\+%]/', '', $text)).'*';
        $wide = $narrow * 2.5;
        $x = 0.0;
        $bars = '';
        foreach (str_split($text) as $char) {
            foreach (str_split(self::PATTERNS[$char]) as $i => $w) {
                $width = $w === 'w' ? $wide : $narrow;
                if ($i % 2 === 0) {
                    $bars .= sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d"/>', $x, $width, $height);
                }
                $x += $width;
            }
            $x += $narrow;
        }

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%.0f" height="%d" viewBox="0 0 %.2f %d" role="img" aria-label="Barcode %s"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g></svg>',
            $x, $height, $x, $height, e(trim($text, '*')), $bars);
    }
}

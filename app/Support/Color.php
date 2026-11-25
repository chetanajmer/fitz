<?php

namespace App\Support;

/**
 * Small helpers for #rrggbb colours: validation, mixing and WCAG contrast.
 * Used to turn a gym's chosen dashboard colours into a safe, readable theme.
 */
class Color
{
    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Mix two colours; `$weight` is the share of `$other` (0–1).
     */
    public static function mix(string $hex, string $other, float $weight): string
    {
        [$r1, $g1, $b1] = self::rgb($hex);
        [$r2, $g2, $b2] = self::rgb($other);
        $channel = fn (int $a, int $b) => (int) round($a + ($b - $a) * $weight);

        return sprintf('#%02x%02x%02x', $channel($r1, $r2), $channel($g1, $g2), $channel($b1, $b2));
    }

    /** WCAG relative luminance, 0 (black) to 1 (white). */
    public static function luminance(string $hex): float
    {
        $linear = fn (int $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = self::rgb($hex);

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }

    /** WCAG contrast ratio between two colours, 1 to 21. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }
}

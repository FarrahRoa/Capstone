<?php

namespace App\Support;

/**
 * Normalizes per-space reservation guideline / facility fields stored on {@see \App\Models\Space}.
 *
 * Quantities: whiteboard_count, projector_count, computer_count, dvd_player_count, sound_system_count (integers ≥ 0).
 * Internet: internet_options — array of allowed labels; "None" is mutually exclusive with other options.
 */
final class SpaceGuidelineDetails
{
    /** @var list<string> */
    public const INTERNET_OPTION_VALUES = ['LAN Cable', 'School Wifi', 'Boardroom Wifi', 'None'];

    /** @var list<string> */
    public const QUANTITY_KEYS = [
        'whiteboard_count',
        'projector_count',
        'computer_count',
        'dvd_player_count',
        'sound_system_count',
    ];

    /** Legacy yes/no keys → quantity key */
    public const LEGACY_AMENITY_TO_COUNT = [
        'whiteboard' => 'whiteboard_count',
        'projector' => 'projector_count',
        'computer' => 'computer_count',
        'dvd_player' => 'dvd_player_count',
        'sound_system' => 'sound_system_count',
    ];

    /** @var list<string> */
    public const TEXT_KEYS = ['location', 'seating_capacity_note', 'others'];

    /**
     * Merge legacy yes/no amenity keys into canonical quantity / internet shape (read path + input coalescing).
     *
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    public static function migrateLegacy(array $in): array
    {
        $out = $in;

        foreach (self::LEGACY_AMENITY_TO_COUNT as $legacy => $countKey) {
            if (array_key_exists($countKey, $out)) {
                continue;
            }
            if (! array_key_exists($legacy, $out)) {
                continue;
            }
            $v = $out[$legacy];
            if ($v === 'yes' || $v === true || $v === 1 || $v === '1') {
                $out[$countKey] = 1;
            } elseif ($v === 'no' || $v === false || $v === 0 || $v === '0') {
                $out[$countKey] = 0;
            }
        }

        if (! array_key_exists('internet_options', $out) && array_key_exists('internet', $out)) {
            $iv = $out['internet'];
            if ($iv === 'no' || $iv === false || $iv === 0 || $iv === '0') {
                $out['internet_options'] = ['None'];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $options
     */
    public static function internetOptionsExclusiveNoneInvalid(?array $options): bool
    {
        if ($options === null || $options === []) {
            return false;
        }
        $unique = array_values(array_unique($options));

        return in_array('None', $unique, true) && count($unique) > 1;
    }

    /**
     * Sanitize internet_options: allowed values only, unique, stable order.
     *
     * @param  mixed  $raw
     * @return list<string>|null
     */
    public static function sanitizeInternetOptions(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (! is_array($raw)) {
            return null;
        }

        $order = array_flip(self::INTERNET_OPTION_VALUES);
        $picked = [];
        foreach ($raw as $item) {
            $s = trim((string) $item);
            if (! in_array($s, self::INTERNET_OPTION_VALUES, true)) {
                continue;
            }
            $picked[$s] = true;
        }

        if ($picked === []) {
            return null;
        }

        $keys = array_keys($picked);
        usort($keys, fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        return array_values($keys);
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>|null  null when empty after normalization
     */
    public static function normalize(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $raw = self::migrateLegacy($raw);

        $out = [];

        foreach (self::TEXT_KEYS as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $t = trim((string) $raw[$key]);
            if ($t === '') {
                continue;
            }
            $max = $key === 'others' ? 5000 : 2000;
            $out[$key] = mb_substr($t, 0, $max);
        }

        foreach (self::QUANTITY_KEYS as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $v = $raw[$key];
            if ($v === '' || $v === null) {
                continue;
            }
            if (is_string($v) && ! is_numeric($v)) {
                continue;
            }
            $n = (int) $v;
            if ($n < 0) {
                $n = 0;
            }
            if ($n > 99) {
                $n = 99;
            }
            $out[$key] = $n;
        }

        $io = self::sanitizeInternetOptions($raw['internet_options'] ?? null);
        if ($io !== null && $io !== [] && ! self::internetOptionsExclusiveNoneInvalid($io)) {
            $out['internet_options'] = $io;
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function forApi(?array $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $merged = self::migrateLegacy($stored);

        return self::normalize($merged) ?? [];
    }
}

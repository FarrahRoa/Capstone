<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Human-readable reservation date/time labels (app timezone). Does not alter stored values.
 */
final class ReservationDisplayFormat
{
    public static function date(DateTimeInterface|string $dt): string
    {
        return self::carbon($dt)->format('M d Y');
    }

    public static function time(DateTimeInterface|string $dt): string
    {
        return self::carbon($dt)->format('g:i A');
    }

    /**
     * Single-day: "Apr 16 2026 · 9:30 AM – 5:30 PM".
     * Multi-day: start date/time – end date/time with the same pattern.
     */
    public static function dateAndTimes(DateTimeInterface|string $start, DateTimeInterface|string $end): string
    {
        $s = self::carbon($start);
        $e = self::carbon($end);

        if ($s->toDateString() === $e->toDateString()) {
            return self::date($s).' · '.$s->format('g:i A').' – '.$e->format('g:i A');
        }

        return self::date($s).' · '.$s->format('g:i A').' – '.self::date($e).' · '.$e->format('g:i A');
    }

    public static function dateTimeLine(DateTimeInterface|string $dt): string
    {
        $c = self::carbon($dt);

        return self::date($c).' · '.$c->format('g:i A');
    }

    private static function carbon(DateTimeInterface|string $dt): Carbon
    {
        if ($dt instanceof DateTimeInterface) {
            return Carbon::instance($dt)->timezone(config('app.timezone'));
        }

        return Carbon::parse($dt)->timezone(config('app.timezone'));
    }
}

<?php

namespace App\Helpers;

class Jalali
{
    private static array $enToFa = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'];

    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = $gy - 1600; $gm2 = $gm - 1; $gd2 = $gd - 1;
        $g_day_no = 365*$gy2 + intdiv($gy2+3,4) - intdiv($gy2+99,100) + intdiv($gy2+399,400);
        $g_day_no += $g_d_m[$gm2] + $gd2;
        if ($gm2 > 1 && (($gy%4===0 && $gy%100!==0) || $gy%400===0)) $g_day_no++;
        $j_day_no = $g_day_no - 79;
        $j_np = intdiv($j_day_no, 12053); $j_day_no %= 12053;
        $jy = 979 + 33*$j_np + 4*intdiv($j_day_no, 1461);
        $j_day_no %= 1461;
        if ($j_day_no >= 366) { $jy += intdiv($j_day_no-1, 365); $j_day_no = ($j_day_no-1) % 365; }
        if ($j_day_no < 186) { $jm = 1 + intdiv($j_day_no, 31); $jd = 1 + ($j_day_no % 31); }
        else { $jm = 7 + intdiv($j_day_no-186, 30); $jd = 1 + ($j_day_no-186) % 30; }
        return [$jy, $jm, $jd];
    }

    public static function format(\DateTimeInterface|string|null $dt, bool $withTime = true): string
    {
        if (!$dt) return '—';
        if (is_string($dt)) $dt = new \DateTime($dt);
        [$jy, $jm, $jd] = self::toJalali((int)$dt->format('Y'), (int)$dt->format('m'), (int)$dt->format('d'));
        $out = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        if ($withTime) $out .= ' ' . $dt->format('H:i');
        return strtr($out, self::$enToFa);
    }

    public static function now(): string
    {
        return self::format(new \DateTime(), true);
    }

    public static function faNum(int|float|null $n): string
    {
        if ($n === null) return '—';
        return strtr(number_format((int)$n), array_merge(self::$enToFa, [','=>'٬']));
    }
}

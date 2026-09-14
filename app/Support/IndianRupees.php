<?php

namespace App\Support;

final class IndianRupees
{
    public static function format(float|int|string|null $amount): string
    {
        $n = self::toFloat($amount);
        if ($n === null) {
            return '—';
        }

        return '₹ '.number_format($n, 2);
    }

    public static function inWords(float|int|string|null $amount): string
    {
        $n = self::toFloat($amount);
        if ($n === null || $n <= 0) {
            return '';
        }

        $rupees = (int) floor($n);
        $paise = (int) round(($n - $rupees) * 100);
        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }

        $words = self::numToWords($rupees);
        if ($paise > 0) {
            $words .= ' and '.self::numToWords($paise).' paise';
        }

        $words = trim((string) preg_replace('/\s+/', ' ', $words));
        if ($words === '') {
            return '';
        }

        return '₹ '.ucfirst($words).' only';
    }

    private static function toFloat(float|int|string|null $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $n = (float) str_replace(',', '', (string) $amount);
        if (! is_finite($n)) {
            return null;
        }

        return $n;
    }

    private static function numToWords(int $x): string
    {
        $a = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $b = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        $convert = function (int $n) use (&$convert, $a, $b): string {
            if ($n < 20) {
                return $a[$n];
            }
            if ($n < 100) {
                return $b[(int) floor($n / 10)].($n % 10 ? '-'.$a[$n % 10] : '');
            }
            if ($n < 1000) {
                return $a[(int) floor($n / 100)].' hundred'.($n % 100 ? ' and '.$convert($n % 100) : '');
            }
            if ($n < 100000) {
                return trim($convert((int) floor($n / 1000)).' thousand '.$convert($n % 1000));
            }
            if ($n < 10000000) {
                return trim($convert((int) floor($n / 100000)).' lakh '.$convert($n % 100000));
            }

            return trim($convert((int) floor($n / 10000000)).' crore '.$convert($n % 10000000));
        };

        return trim((string) preg_replace('/\s+/', ' ', $convert($x)));
    }
}

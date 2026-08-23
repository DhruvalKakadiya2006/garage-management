<?php

namespace App\Support;

/**
 * Convert rupee amounts to their Indian English word form, using the
 * Indian numbering system (crore / lakh / thousand) as used on GST
 * invoices and statements.
 */
class IndianCurrency
{
    private const ONES = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    /**
     * Convert a rupee amount to words, e.g. 340.16 -> "Rupees Three
     * Hundred Forty and Paise Sixteen Only".
     */
    public static function inWords(float $amount): string
    {
        $amount = round((float) $amount, 2);

        $negative = $amount < 0;
        $amount = abs($amount);

        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);
        if ($paise === 100) {
            $rupees += 1;
            $paise = 0;
        }

        $text = 'Rupees '.self::numberToWords($rupees);

        if ($paise > 0) {
            $text .= ' and Paise '.self::numberToWords($paise);
        }

        $text .= ' Only';

        return $negative ? 'Minus '.$text : $text;
    }

    /**
     * Convert an integer to words using Indian numbering groups.
     */
    public static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $words = '';

        $crore = intdiv($number, 10000000);
        $lakh = intdiv($number % 10000000, 100000);
        $thousand = intdiv($number % 100000, 1000);
        $rest = $number % 1000;

        if ($crore > 0) {
            $words .= self::toWordsUnder1000($crore).' Crore ';
        }
        if ($lakh > 0) {
            $words .= self::toWordsUnder1000($lakh).' Lakh ';
        }
        if ($thousand > 0) {
            $words .= self::toWordsUnder1000($thousand).' Thousand ';
        }
        if ($rest > 0) {
            $words .= self::toWordsUnder1000($rest);
        }

        return trim($words);
    }

    private static function toWordsUnder1000(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            $ten = intdiv($number, 10);
            $one = $number % 10;

            return self::TENS[$ten].($one ? ' '.self::ONES[$one] : '');
        }

        $hundred = intdiv($number, 100);
        $rest = $number % 100;

        return self::ONES[$hundred].' Hundred'.($rest ? ' '.self::toWordsUnder1000($rest) : '');
    }
}

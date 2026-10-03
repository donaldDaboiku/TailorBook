<?php

namespace App\Services;

class PhoneNormalizer
{
    /**
     * Digits for https://wa.me/<number>. Nigerian local numbers become 234…
     * Other numbers keep their dialed country code when present.
     */
    public static function forWhatsApp(string $phone, string $country = 'NG'): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strtoupper($country) !== 'NG') {
            return $digits;
        }

        if (str_starts_with($digits, '234')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '234'.$digits;
        }

        return $digits;
    }

    public static function whatsappUrl(string $phone, string $country = 'NG', ?string $text = null): string
    {
        $number = self::forWhatsApp($phone, $country);
        $url = 'https://wa.me/'.$number;

        if ($text !== null && $text !== '') {
            $url .= '?text='.rawurlencode($text);
        }

        return $url;
    }
}

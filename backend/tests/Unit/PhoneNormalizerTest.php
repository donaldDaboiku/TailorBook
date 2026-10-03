<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_nigerian_local_numbers_become_country_code_format(): void
    {
        $this->assertSame('2348031234567', PhoneNormalizer::forWhatsApp('08031234567'));
        $this->assertSame('2348031234567', PhoneNormalizer::forWhatsApp('0803 123 4567'));
        $this->assertSame('2348031234567', PhoneNormalizer::forWhatsApp('+234 803 123 4567'));
        $this->assertSame('2348031234567', PhoneNormalizer::forWhatsApp('8031234567'));
    }

    public function test_non_nigerian_numbers_are_not_rewritten(): void
    {
        $this->assertSame('447911123456', PhoneNormalizer::forWhatsApp('+44 7911 123456', 'GB'));
    }

    public function test_whatsapp_url_encodes_optional_text(): void
    {
        $this->assertSame(
            'https://wa.me/2348031234567?text=Hello%20Grace',
            PhoneNormalizer::whatsappUrl('08031234567', 'NG', 'Hello Grace'),
        );
    }
}

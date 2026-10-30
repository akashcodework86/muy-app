<?php

namespace Tests\Unit;

use App\Support\HubBatchImportNormalizer as Normalizer;
use PHPUnit\Framework\TestCase;

class HubBatchImportNormalizerTest extends TestCase
{
    public function test_phone_is_reduced_to_last_ten_digits(): void
    {
        $this->assertSame('9876543210', Normalizer::phone('+91 98765 43210'));
    }

    public function test_application_number_is_normalized_without_changing_identity(): void
    {
        $this->assertSame('90C0000C', Normalizer::applicationNo(' 90c 0000c '));
    }

    public function test_rbi_prefixed_application_numbers_generate_identity_keys(): void
    {
        $keys = Normalizer::applicationIdentityKeys('RBI1770186398');
        $this->assertContains('RBI1770186398', $keys);
        $this->assertContains('1770186398', $keys);
    }
}

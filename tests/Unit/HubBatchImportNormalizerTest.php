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

    public function test_known_application_number_variants_are_generated_after_exact_form(): void
    {
        $this->assertContains('A0C0000C', Normalizer::applicationVariants('90C0000C'));
        $this->assertContains('A080000C', Normalizer::applicationVariants('4080000C'));
    }
}

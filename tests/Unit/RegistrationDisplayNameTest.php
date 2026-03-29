<?php

namespace Tests\Unit;

use App\Support\RegistrationDisplayName;
use PHPUnit\Framework\TestCase;

class RegistrationDisplayNameTest extends TestCase
{
    public function test_numeric_local_part_returns_fallback(): void
    {
        $this->assertSame('XU User', RegistrationDisplayName::fromEmail('20220024802@my.xu.edu.ph'));
    }

    public function test_dot_separated_local_part_is_title_cased(): void
    {
        $this->assertSame('Juan Dela Cruz', RegistrationDisplayName::fromEmail('juan.dela.cruz@xu.edu.ph'));
    }

    public function test_underscore_separated_local_part_is_title_cased(): void
    {
        $this->assertSame('Maria Santos', RegistrationDisplayName::fromEmail('maria_santos@xu.edu.ph'));
    }

    public function test_single_word_local_part_is_title_cased(): void
    {
        $this->assertSame('Faculty', RegistrationDisplayName::fromEmail('faculty@xu.edu.ph'));
    }

    public function test_empty_local_part_returns_fallback(): void
    {
        $this->assertSame('XU User', RegistrationDisplayName::fromEmail('@xu.edu.ph'));
    }
}

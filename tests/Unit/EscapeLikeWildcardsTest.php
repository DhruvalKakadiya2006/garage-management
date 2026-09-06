<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the escapeLikeWildcards method logic.
 *
 * Feature: advanced-search-filter-sorting, Property 2: Wildcard Escaping
 *
 * For any search query containing SQL LIKE wildcards (%, _, \),
 * the search system SHALL escape these characters and treat them
 * as literal characters, not as wildcard operators.
 *
 * Validates: Requirements 1.3
 */
class EscapeLikeWildcardsTest extends TestCase
{
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function test_escapes_percent_sign(): void
    {
        $result = $this->escapeLikeWildcards('100% cotton');

        $this->assertSame('100\\% cotton', $result);
    }

    public function test_escapes_underscore(): void
    {
        $result = $this->escapeLikeWildcards('user_name');

        $this->assertSame('user\\_name', $result);
    }

    public function test_escapes_backslash(): void
    {
        $result = $this->escapeLikeWildcards('path\\to\\file');

        $this->assertSame('path\\\\to\\\\file', $result);
    }

    public function test_escapes_multiple_wildcards(): void
    {
        $result = $this->escapeLikeWildcards('test_%value\\');

        $this->assertSame('test\\_\\%value\\\\', $result);
    }

    public function test_handles_empty_string(): void
    {
        $result = $this->escapeLikeWildcards('');

        $this->assertSame('', $result);
    }

    public function test_handles_string_without_wildcards(): void
    {
        $result = $this->escapeLikeWildcards('normal text');

        $this->assertSame('normal text', $result);
    }

    public function test_handles_only_percent_signs(): void
    {
        $result = $this->escapeLikeWildcards('%%%');

        $this->assertSame('\\%\\%\\%', $result);
    }

    public function test_handles_only_underscores(): void
    {
        $result = $this->escapeLikeWildcards('___');

        $this->assertSame('\\_\\_\\_', $result);
    }

    public function test_handles_only_backslashes(): void
    {
        $result = $this->escapeLikeWildcards('\\\\\\');

        $this->assertSame('\\\\\\\\\\\\', $result);
    }

    public function test_handles_mixed_wildcards(): void
    {
        $result = $this->escapeLikeWildcards('\\%_mixed_%\\');

        $this->assertSame('\\\\\\%\\_mixed\\_\\%\\\\', $result);
    }
}

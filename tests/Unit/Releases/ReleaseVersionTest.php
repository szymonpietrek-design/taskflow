<?php

namespace Tests\Unit\Releases;

use App\Support\Releases\ReleaseVersion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReleaseVersionTest extends TestCase
{
    public function test_it_parses_a_strict_three_part_version(): void
    {
        $version = ReleaseVersion::parse('10.2.17');

        $this->assertSame(10, $version->major);
        $this->assertSame(2, $version->minor);
        $this->assertSame(17, $version->patch);
        $this->assertSame('10.2.17', $version->toString());
    }

    public function test_it_compares_version_components_numerically(): void
    {
        $this->assertGreaterThan(0, ReleaseVersion::parse('1.10.0')->compareTo(ReleaseVersion::parse('1.2.99')));
        $this->assertLessThan(0, ReleaseVersion::parse('1.2.0')->compareTo(ReleaseVersion::parse('2.0.0')));
        $this->assertSame(0, ReleaseVersion::parse('1.0.0')->compareTo(ReleaseVersion::parse('1.0.0')));
    }

    public function test_it_rejects_invalid_or_unsupported_versions(): void
    {
        foreach (['', '1.0', 'v1.0.0', '01.0.0', '1.00.0', '1.0.03', '-1.0.0', '1.0.0-beta.1', '1.0.0+build.1', '1.0.0 ', '4294967296.0.0'] as $invalid) {
            try {
                ReleaseVersion::parse($invalid);
                $this->fail('Expected invalid version to be rejected: '.$invalid);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_it_supports_the_maximum_storable_component(): void
    {
        $this->assertSame('4294967295.0.0', ReleaseVersion::parse('4294967295.0.0')->toString());
    }
}

<?php

namespace App\Support\Releases;

use InvalidArgumentException;

/**
 * Strict SemVer core (MAJOR.MINOR.PATCH). Pre-release/build metadata are
 * deliberately excluded in stage 4A; they can be added as a separate step.
 */
final readonly class ReleaseVersion
{
    private function __construct(
        public int $major,
        public int $minor,
        public int $patch,
    ) {}

    public static function parse(string $version): self
    {
        if (! preg_match('/\A(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\z/D', $version, $matches)) {
            throw new InvalidArgumentException(__('Version must use MAJOR.MINOR.PATCH (for example 1.2.3), with no leading zeros.'));
        }

        $parts = array_slice($matches, 1, 3);

        foreach ($parts as $part) {
            if (strlen($part) > 10 || (strlen($part) === 10 && strcmp($part, '4294967295') > 0)) {
                throw new InvalidArgumentException(__('Version components must be between 0 and 4294967295.'));
            }
        }

        return new self((int) $parts[0], (int) $parts[1], (int) $parts[2]);
    }

    public function toString(): string
    {
        return $this->major.'.'.$this->minor.'.'.$this->patch;
    }

    public function compareTo(self $other): int
    {
        return [$this->major, $this->minor, $this->patch]
            <=> [$other->major, $other->minor, $other->patch];
    }
}

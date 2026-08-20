<?php

namespace App\Services;

class PersonNameMatch
{
    public function __construct(
        public readonly string $status,
        public readonly ?object $model = null,
        public readonly ?string $label = null,
        public readonly array $candidates = [],
        public readonly ?string $message = null,
    ) {}

    public function isUnique(): bool
    {
        return $this->status === 'unique';
    }

    public function isNone(): bool
    {
        return $this->status === 'none';
    }

    public function isAmbiguous(): bool
    {
        return $this->status === 'ambiguous';
    }
}

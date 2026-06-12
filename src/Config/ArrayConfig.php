<?php

declare(strict_types=1);

namespace FriendsOfBehat\TestContext\Config;

use Behat\Config\ConfigInterface;

final class ArrayConfig implements ConfigInterface
{
    /** @param array<mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    /** @return array<mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}

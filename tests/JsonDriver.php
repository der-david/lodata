<?php

namespace Flat3\Lodata\Tests;

use PHPUnit\Framework\Assert;
use Spatie\Snapshots\Driver;
use Spatie\Snapshots\Exceptions\CantBeSerialized;

class JsonDriver implements Driver
{
    /**
     * @param  mixed  $data
     * @return string
     * @throws CantBeSerialized
     */
    public function serialize($data): string
    {
        if (is_string($data)) {
            $data = json_decode($data);
        }

        if (is_resource($data)) {
            throw new CantBeSerialized('Resources can not be serialized to json');
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    public function extension(): string
    {
        return 'json';
    }

    public function match($expected, $actual)
    {
        if (is_array($actual)) {
            $actual = json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        }

        Assert::assertJsonStringEqualsJsonString($expected, $actual);
    }
}

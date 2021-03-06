<?php

namespace Flat3\Lodata\Tests\Unit\Protocol;

use Flat3\Lodata\Exception\Protocol\MethodNotAllowedException;
use Flat3\Lodata\Tests\Request;
use Flat3\Lodata\Tests\TestCase;

class MethodTest extends TestCase
{
    public function test_rejects_bad_method()
    {
        try {
            $this->req(
                Request::factory()
                    ->method('PATCH')
            );
        } catch (MethodNotAllowedException $e) {
            $this->assertProtocolExceptionSnapshot($e);
        }
    }
}

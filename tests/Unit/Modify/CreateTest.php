<?php

namespace Flat3\Lodata\Tests\Unit\Modify;

use Flat3\Lodata\Tests\Request;
use Flat3\Lodata\Tests\TestCase;

class CreateTest extends TestCase
{
    public function test_create()
    {
        $this->withFlightModel();

        $this->assertJsonResponse(
            Request::factory()
                ->path('/flights')
                ->post()
                ->body([
                    'origin' => 'lhr',
                ])
        );
    }

    public function test_create_content_type_error()
    {
        $this->withFlightModel();

        $this->assertNotAcceptable(
            Request::factory()
                ->path('/flights')
                ->text()
                ->post()
                ->body([
                    'origin' => 'lhr',
                ])
        );
    }

    public function test_create_related_entity()
    {
        $this->withFlightModel();

        $this->assertJsonResponse(
            Request::factory()
                ->path('/flights(1)/passengers')
                ->post()
                ->body([
                    'name' => 'Henry Horse',
                ])
        );

        $this->assertJsonResponse(
            Request::factory()
                ->path('/flights(1)/passengers')
        );
    }

    public function test_create_ref()
    {
        $this->withFlightModel();

        $this->assertJsonResponse(
            Request::factory()
                ->path('/flights/$ref')
                ->post()
                ->body([
                    'origin' => 'lhr',
                ])
        );
    }
}
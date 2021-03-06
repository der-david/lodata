<?php

namespace Flat3\Lodata\Tests\Unit\Protocol;

use Flat3\Lodata\Controller\Async;
use Flat3\Lodata\Tests\JsonDriver;
use Flat3\Lodata\Tests\Request;
use Flat3\Lodata\Tests\TestCase;
use Flat3\Lodata\Transaction\Metadata\Full;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class AsyncTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withFlightModel();
    }

    public function async_request(Request $request)
    {
        $queue = Queue::fake();
        $disk = $this->getDisk();

        $request->header('prefer', 'respond-async');

        $acceptedException = $this->assertAccepted(
            $request
        );

        $location = parse_url($acceptedException->toResponse()->headers->get('location'), PHP_URL_PATH);

        $this->assertAccepted(
            Request::factory()
                ->path($location, false)
        );

        /** @var Async $job */
        $job = collect($queue->pushedJobs())->flatten(1)->first()['job'];
        $job->handle();

        $this->assertStoredResponseMetadata($disk->get($job->ns('meta')));

        if ($request->headers['accept'] === 'application/json') {
            $this->assertMatchesSnapshot($disk->get($job->ns('data')), new JsonDriver());

            $this->assertResponseMetadata($this->assertJsonResponse(
                Request::factory()
                    ->path($location, false)
            ));
        } else {
            $this->assertMatchesSnapshot($disk->get($job->ns('data')));

            $this->assertResponseMetadata($this->assertTextResponse(
                Request::factory()
                    ->path($location, false)
            ));
        }

        $this->assertNotFound(
            Request::factory()
                ->path($location, false)
        );
    }

    public function test_cancellation()
    {
        $queue = Queue::fake();

        $acceptedException = $this->assertAccepted(
            Request::factory()
                ->header('prefer', 'respond-async')
        );

        $location = parse_url($acceptedException->toResponse()->headers->get('location'), PHP_URL_PATH);

        $this->assertMetadataResponse(
            Request::factory()
                ->delete()
                ->path($location, false)
        );

        $this->assertNotFound(
            Request::factory()
                ->path($location, false)
        );

        /** @var Async $job */
        $job = collect($queue->pushedJobs())->flatten(1)->first()['job'];
        $job->handle();

        $this->assertFalse($job->getDisk()->exists($job->getMetaPath()));
        $this->assertFalse($job->getDisk()->exists($job->getDataPath()));
    }

    public function test_error()
    {
        $queue = Queue::fake();
        $disk = $this->getDisk();

        $acceptedException = $this->assertAccepted(
            Request::factory()
                ->path('/nonexistent')
                ->header('prefer', 'respond-async')
        );

        $location = parse_url($acceptedException->toResponse()->headers->get('location'), PHP_URL_PATH);

        $this->assertAccepted(
            Request::factory()
                ->path($location, false)
        );

        /** @var Async $job */
        $job = collect($queue->pushedJobs())->flatten(1)->first()['job'];
        $job->handle();

        $this->assertStoredResponseMetadata($disk->get($job->ns('meta')));

        $response = $this->assertJsonMetadataResponse(
            Request::factory()
                ->path($location, false)
        );

        $response->streamedContent();

        $this->assertNotFound(
            Request::factory()
                ->path($location, false)
        );
    }

    public function test_callback()
    {
        $queue = Queue::fake();
        $disk = $this->getDisk();
        Http::fake();

        $url = 'http://localhost/example';

        $acceptedException = $this->assertAccepted(
            Request::factory()
                ->header('prefer', 'respond-async,callback;url="'.$url.'"')
        );

        $location = parse_url($acceptedException->toResponse()->headers->get('location'), PHP_URL_PATH);

        $this->assertAccepted(
            Request::factory()
                ->path($location, false)
        );

        /** @var Async $job */
        $job = collect($queue->pushedJobs())->flatten(1)->first()['job'];
        $job->handle();

        Http::assertSent(function ($request) use ($url) {
            return $request->url() == $url;
        });

        $this->assertMatchesSnapshot($disk->get($job->ns('data')), new JsonDriver());
        $this->assertStoredResponseMetadata($disk->get($job->ns('meta')));

        $this->assertResponseMetadata($this->assertJsonResponse(
            Request::factory()
                ->path($location, false)
        ));

        $this->assertNotFound(
            Request::factory()
                ->path($location, false)
        );
    }

    public function test_async()
    {
        $this->async_request(
            Request::factory()
        );
    }

    public function test_async_metadata()
    {
        $this->async_request(
            Request::factory()
                ->xml()
                ->path('/$metadata')
        );
    }

    public function test_async_entityset()
    {
        $this->async_request(
            Request::factory()
                ->path('/flights')
        );
    }

    public function test_async_full_metadata()
    {
        $this->async_request(
            Request::factory()
                ->path('/flights')
                ->metadata(Full::name)
        );
    }

    public function test_async_batch()
    {
        $this->async_request(
            Request::factory()
                ->path('/$batch')
                ->header('content-type', 'multipart/mixed; boundary=batch_36522ad7-fc75-4b56-8c71-56071383e77b')
                ->post()
                ->multipart(<<<MULTIPART
--batch_36522ad7-fc75-4b56-8c71-56071383e77b
Content-Type: application/http

GET flights(1)
Host: localhost


--batch_36522ad7-fc75-4b56-8c71-56071383e77b
MULTIPART
                )
        );
    }

    public function test_async_batch_json()
    {
        $this->async_request(
            Request::factory()
                ->path('/$batch')
                ->post()
                ->body([
                    'requests' => [
                        [
                            'id' => 0,
                            'method' => 'get',
                            'url' => 'flights(1)'
                        ]
                    ]
                ])
        );
    }

    public function test_async_batch_service_metadata()
    {
        $this->async_request(
            Request::factory()
                ->path('/$batch')
                ->header('content-type', 'multipart/mixed; boundary=batch_36522ad7-fc75-4b56-8c71-56071383e77b')
                ->post()
                ->multipart(<<<'MULTIPART'
--batch_36522ad7-fc75-4b56-8c71-56071383e77b
Content-Type: application/http

GET /odata/$metadata
Host: localhost
Content-Type: application/xml

--batch_36522ad7-fc75-4b56-8c71-56071383e77b
Content-Type: application/http

GET /odata/
Host: localhost
Content-Type: application/json

--batch_36522ad7-fc75-4b56-8c71-56071383e77b
MULTIPART
                )
        );
    }
}
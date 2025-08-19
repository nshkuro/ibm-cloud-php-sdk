<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\ObjectStorage;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Services\ObjectStorage\Client;
use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\Models\Queries\ObjectQuery;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use IBMCloud\Services\ObjectStorage\ValueObjects\StorageClass;
use Mockery;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private TransportInterface $transport;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = Mockery::mock(TransportInterface::class);
        $this->client = new Client($this->transport, 'https://test-cos.example.com');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetServiceInfo(): void
    {
        $this->assertSame('cloud-object-storage', $this->client->getServiceName());
        $this->assertSame('v1', $this->client->getServiceVersion());
        $this->assertSame('https://test-cos.example.com', $this->client->getServiceUrl());
    }

    public function testSetServiceUrl(): void
    {
        $this->client->setServiceUrl('https://new-cos.example.com/');
        
        $this->assertSame('https://new-cos.example.com', $this->client->getServiceUrl());
    }

    public function testStoreObject(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('test/file.txt');
        $content = 'Hello, World!';
        
        $command = ObjectCommand::store($bucket, $key, $content, StorageClass::STANDARD, ['author' => 'test']);

        $expectedResponse = new Response(200, ['ETag' => '"abc123"']);

        $this->transport->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Request $request) {
                return $request->getMethod() === 'PUT'
                    && (string) $request->getUri() === 'https://test-cos.example.com/test-bucket/test%2Ffile.txt'
                    && $request->getBody()->getContents() === 'Hello, World!'
                    && $request->getHeaderLine('Content-Type') === 'application/octet-stream'
                    && $request->getHeaderLine('x-amz-storage-class') === 'STANDARD'
                    && $request->getHeaderLine('x-amz-meta-author') === 'test';
            }))
            ->andReturn($expectedResponse);

        $result = $this->client->store($command);

        $this->assertSame($bucket, $result->bucket);
        $this->assertSame($key, $result->key);
        $this->assertSame('abc123', $result->etag);
        $this->assertSame(StorageClass::STANDARD, $result->storageClass);
    }

    public function testRetrieveObject(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('test/file.txt');
        $query = ObjectQuery::for($bucket, $key);

        $content = 'Retrieved content';
        $expectedResponse = new Response(200, [
            'Content-Type' => 'text/plain',
            'ETag' => '"def456"',
            'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
            'x-amz-meta-author' => 'test-user'
        ], $content);

        $this->transport->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Request $request) {
                return $request->getMethod() === 'GET'
                    && (string) $request->getUri() === 'https://test-cos.example.com/test-bucket/test%2Ffile.txt';
            }))
            ->andReturn($expectedResponse);

        $result = $this->client->retrieve($query);

        $this->assertSame($bucket, $result->bucket);
        $this->assertSame($key, $result->key);
        $this->assertSame($content, $result->content);
        $this->assertSame('text/plain', $result->contentType);
        $this->assertSame('def456', $result->etag);
        $this->assertInstanceOf(\DateTimeInterface::class, $result->lastModified);
        $this->assertSame(['author' => 'test-user'], $result->metadata);
    }

    public function testRetrieveObjectWithRange(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('large-file.txt');
        $query = ObjectQuery::withRange($bucket, $key, 100, 199);

        $partialContent = str_repeat('a', 100);
        $expectedResponse = new Response(206, [
            'Content-Range' => 'bytes 100-199/1000',
            'ETag' => '"range123"'
        ], $partialContent);

        $this->transport->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Request $request) {
                return $request->getMethod() === 'GET'
                    && $request->getHeaderLine('Range') === 'bytes=100-199';
            }))
            ->andReturn($expectedResponse);

        $result = $this->client->retrieve($query);

        $this->assertSame($partialContent, $result->content);
        $this->assertSame(100, $result->contentLength);
    }

    public function testDeleteObject(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('file-to-delete.txt');
        $command = ObjectCommand::delete($bucket, $key);

        $expectedResponse = new Response(204);

        $this->transport->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Request $request) {
                return $request->getMethod() === 'DELETE'
                    && (string) $request->getUri() === 'https://test-cos.example.com/test-bucket/file-to-delete.txt';
            }))
            ->andReturn($expectedResponse);

        $this->client->delete($command);
        
        // If no exception is thrown, the test passes.
        $this->assertTrue(true);
    }

    public function testExistsReturnsTrueForExistingObject(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('existing-file.txt');
        $query = ObjectQuery::for($bucket, $key);

        $expectedResponse = new Response(200);

        $this->transport->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Request $request) {
                return $request->getMethod() === 'HEAD';
            }))
            ->andReturn($expectedResponse);

        $exists = $this->client->exists($query);

        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseForNonExistentObject(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('missing-file.txt');
        $query = ObjectQuery::for($bucket, $key);

        $this->transport->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Not found'));

        $exists = $this->client->exists($query);

        $this->assertFalse($exists);
    }

    public function testStreamObject(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('stream-file.txt');
        $query = ObjectQuery::for($bucket, $key);

        $streamContent = 'Streaming content';
        $stream = Utils::streamFor($streamContent);
        $expectedResponse = new Response(200, [], $stream);

        $this->transport->shouldReceive('send')
            ->once()
            ->andReturn($expectedResponse);

        $resultStream = $this->client->stream($query);

        $this->assertSame($streamContent, $resultStream->getContents());
    }

    public function testStoreThrowsExceptionForDeleteCommand(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('test-file.txt');
        $command = ObjectCommand::delete($bucket, $key);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Command must be a store operation.');

        $this->client->store($command);
    }

    public function testDeleteThrowsExceptionForStoreCommand(): void
    {
        $bucket = BucketName::from('test-bucket');
        $key = ObjectKey::from('test-file.txt');
        $command = ObjectCommand::store($bucket, $key, 'content');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Command must be a delete operation.');

        $this->client->delete($command);
    }
}
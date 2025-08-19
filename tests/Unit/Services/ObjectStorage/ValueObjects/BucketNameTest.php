<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\ObjectStorage\ValueObjects;

use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BucketNameTest extends TestCase
{
    public function testCreateValidBucketName(): void
    {
        $bucketName = new BucketName('my-test-bucket');
        
        $this->assertSame('my-test-bucket', $bucketName->value);
        $this->assertSame('my-test-bucket', $bucketName->toString());
    }

    public function testFromMethod(): void
    {
        $bucketName = BucketName::from('another-bucket');
        
        $this->assertSame('another-bucket', $bucketName->value);
    }

    public function testRejectsEmptyBucketName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bucket name cannot be empty.');
        
        new BucketName('');
    }

    public function testRejectsTooShortBucketName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: ab');
        
        new BucketName('ab');
    }

    public function testRejectsTooLongBucketName(): void
    {
        $longName = str_repeat('a', 64);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: ' . $longName);
        
        new BucketName($longName);
    }

    public function testRejectsUppercaseLetters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: MyBucket');
        
        new BucketName('MyBucket');
    }

    public function testRejectsStartingWithDot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: .bucket');
        
        new BucketName('.bucket');
    }

    public function testRejectsEndingWithDot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: bucket.');
        
        new BucketName('bucket.');
    }

    public function testRejectsStartingWithHyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: -bucket');
        
        new BucketName('-bucket');
    }

    public function testRejectsEndingWithHyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: bucket-');
        
        new BucketName('bucket-');
    }

    public function testRejectsConsecutiveDots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: bucket..name');
        
        new BucketName('bucket..name');
    }

    public function testRejectsIpAddressFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bucket name: 192.168.1.1');
        
        new BucketName('192.168.1.1');
    }

    public function testAcceptsValidNames(): void
    {
        $validNames = [
            'bucket',
            'my-bucket',
            'bucket.name',
            'bucket123',
            'test-bucket-with-numbers-123',
            'a.b.c',
        ];
        
        foreach ($validNames as $name) {
            $bucket = new BucketName($name);
            $this->assertSame($name, $bucket->value);
        }
    }
}
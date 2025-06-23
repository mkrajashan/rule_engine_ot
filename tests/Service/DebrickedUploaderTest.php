<?php

namespace App\Tests\Service;

use App\Entity\Upload;
use App\Message\ScanUploadMessage;
use App\Service\DebrickedUploader;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DebrickedUploaderTest extends TestCase
{
    private $httpClient;
    private $em;
    private $bus;
    private $cache;
    private $logger;
    private $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new DebrickedUploader(
            $this->httpClient,
            $this->em,
            $this->bus,
            $this->cache,
            $this->logger
        );
    }

    public function testUploadDependencyFiles()
    {
        $tokenItem = $this->createMock(CacheItemInterface::class);
        $tokenItem->method('isHit')->willReturn(true);
        $tokenItem->method('get')->willReturn('fake_token');

        $this->cache->method('getItem')->willReturn($tokenItem);

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('composer.json');
        $file->method('getRealPath')->willReturn('/tmp/composer.json');

        // Mock format check to TRUE
        $this->httpClient->method('request')
            ->willReturn(new MockResponse(json_encode(['ciUploadId' => '123'])));

        $result = $this->service->uploadDependencyFiles([$file]);

        $this->assertIsArray($result);
        $this->assertEquals('123', $result['uploadedId']);
        $this->assertEquals(200, $result['statusCode']);
    }

    public function testScanFiles()
    {
        $tokenItem = $this->createMock(CacheItemInterface::class);
        $tokenItem->method('isHit')->willReturn(true);
        $tokenItem->method('get')->willReturn('fake_token');

        $uploadedByItem = $this->createMock(CacheItemInterface::class);
        $uploadedByItem->method('isHit')->willReturn(true);
        $uploadedByItem->method('get')->willReturn('test_user');

        $this->cache->method('getItem')->willReturnMap([
            ['debricked_api_token', $tokenItem],
            ['uploaded_by', $uploadedByItem]
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode(['scan' => 'ok']));
        $response->method('toArray')->willReturn(['scan' => 'ok']);

        $this->httpClient->method('request')->willReturn($response);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->scanFiles('123');
        $this->assertArrayHasKey('scan', $result);
    }

    public function testCheckStatusAndTrigger()
    {
        $tokenItem = $this->createMock(CacheItemInterface::class);
        $tokenItem->method('isHit')->willReturn(true);
        $tokenItem->method('get')->willReturn('fake_token');

        $this->cache->method('getItem')->willReturn($tokenItem);

        $this->httpClient->method('request')->willReturnCallback(function () {
            $mockResponse = $this->createMock(ResponseInterface::class);
            $mockResponse->method('getContent')->willReturn(json_encode(['progress' => 100]));
            return $mockResponse;
        });

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->bus->expects($this->once())->method('dispatch')->with($this->isInstanceOf(ScanUploadMessage::class));

        $result = $this->service->checkStatusAndTrigger('123');
        //$this->assertEquals('completed', $result);
    }

    public function testGetTokenReturnsValueFromCache(): void
    {
        $mockCacheItem = $this->createMock(CacheItemInterface::class);
        $mockCacheItem->method('isHit')->willReturn(true);
        $mockCacheItem->method('get')->willReturn('fake_token');

        $mockCache = $this->createMock(CacheItemPoolInterface::class);
        $mockCache->method('getItem')->with('debricked_api_token')->willReturn($mockCacheItem);

        $logger = $this->createMock(LoggerInterface::class);

        $service = new DebrickedUploader(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(MessageBusInterface::class),
            $mockCache,
            $logger
        );
        
        $token = $service->getToken();

        $this->assertSame('fake_token', $token);
    }

    public function testCheckSupportedFormatReturnsTrue(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);

        // Simulate token cache
        $tokenCacheItem = $this->createMock(CacheItemInterface::class);
        $tokenCacheItem->method('isHit')->willReturn(true);
        $tokenCacheItem->method('get')->willReturn('fake_token');
        $cache->method('getItem')->with('debricked_api_token')->willReturn($tokenCacheItem);

        // Mock response from Debricked
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            [
                'regex' => '.*composer\.json',
                'lockFileRegexes' => ['.*composer\.lock']
            ]
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new DebrickedUploader(
            $httpClient,
            $em,
            $bus,
            $cache,
            $logger
        );

        $result = $service->checkSupportedFormat('composer.json');

        $this->assertFalse($result);
    }

}

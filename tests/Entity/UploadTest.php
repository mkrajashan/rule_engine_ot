<?php

namespace App\Tests\Entity;

use App\Entity\Upload;
use PHPUnit\Framework\TestCase;

class UploadTest extends TestCase
{
    public function testUploadEntity()
    {
        $upload = new Upload();
        $now = new \DateTime();
        
        $upload->setFilename('composer.lock');
        $upload->setStatus('completed');
        $upload->setCiUploadId('12345');
        $upload->setUploadedAt($now);
        $upload->setScanResult(['vulnerabilitiesFound' => 3]);
        
        $this->assertEquals('composer.lock', $upload->getFilename());
        $this->assertEquals('completed', $upload->getStatus());
        $this->assertEquals('12345', $upload->getCiUploadId());
        
        // Compare dates without microseconds
        $this->assertEquals(
            $now->format('Y-m-d H:i:s'), 
            $upload->getUploadedAt()->format('Y-m-d H:i:s'),
            'Upload time should match within seconds'
        );
        
        $this->assertEquals(3, $upload->getScanResult()['vulnerabilitiesFound']);
        $this->assertNull($upload->getId());
    }
    
    public function testFailedUpload()
    {
        $upload = new Upload();
        $upload->setStatus('failed');
        //$upload->setError('Connection timeout');
        
        $this->assertEquals('failed', $upload->getStatus());
        //$this->assertEquals('Connection timeout', $upload->getError());
    }
}
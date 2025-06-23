<?php
namespace App\Message;

class ScanUploadMessage
{
    public function __construct(
        public readonly int $uploadId,
        public readonly string $ciUploadId,
    ) {}
}

<?php
namespace App\Message;

/**
 * Message class used to trigger a scan via Symfony Messenger after file upload.
 *
 * @package App\Message
 */
class ScanUploadMessage
{
    public function __construct(
        public readonly int $uploadId,
        public readonly string $ciUploadId,
    ) {}
}

<?php

namespace App\MessageHandler;

use App\Message\ScanUploadMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Upload;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Mime\Part\DataPart;
use App\Service\RuleEvaluator;
use Psr\Log\LoggerInterface;

/**
 * Symfony Messenger handler for ScanUploadMessage.
 * Triggers the actual scan on Debricked using the ciUploadId.
 *
 * @package App\MessageHandler
 */
#[AsMessageHandler]
class ScanUploadMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private RuleEvaluator $ruleEvaluator,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ScanUploadMessage $message)
    {
        $this->logger->info('Invoke the Scheduler '.$message->uploadId);
        $upload = $this->em->getRepository(Upload::class)->find($message->uploadId);
        
        if (!$upload) { 
            $this->logger->error('Upload not found', ['id' => $message->uploadId]);
            return;
        }

        try {            
            // Trigger rule evaluation
            $this->logger->info('Trigger Rule Evaluation');
            $this->ruleEvaluator->evaluate($upload);

        } catch (\Exception $e) {
            $this->logger->error('Trigger Rule Evaluation error'.$e->getMessage());
            $this->handleError($upload, $e);
        }
    }


    private function handleError(Upload $upload, \Exception $e): void
    {
        $upload->setStatus('failed');
        $this->em->flush();
        
        $this->logger->error('Scan processing failed', [
            'uploadId' => $upload->getId(),
            'error' => $e->getMessage()
        ]);
        
        $this->ruleEvaluator->evaluate($upload);
    }
}
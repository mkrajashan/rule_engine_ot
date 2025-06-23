<?php

namespace App\Tests\Service;

use App\Entity\Upload;
use App\Service\RuleEvaluator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Psr\Log\LoggerInterface;

class RuleEvaluatorTest extends TestCase
{
    private $mailer;
    private $notifier;
    private $logger;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testAutomationRulesProcessing()
    {
        $upload = new Upload();
        $upload->setScanResult([
            'automationRules' => [
                [
                    'hasCves' => true,
                    'triggered' => true,
                    'ruleDescription' => 'Test rule description',
                    'ruleActions' => ['sendEmail'],
                    'ruleLink' => 'https://example.com',
                    'triggerEvents' => [
                        [
                            'cve' => 'CVE-2023-1234', 
                            'cvss3' => 8.5,
                            'cveLink' => 'https://example.com/cve',
                            'dependency' => 'test/package'
                        ]
                    ]
                ]
            ]
        ]);

        $this->mailer->expects($this->once())->method('send');
        
        $evaluator = new RuleEvaluator(
            $this->mailer,
            $this->notifier,
            $this->logger,
            'admin@example.com'
        );
        
        $evaluator->evaluate($upload);
    }
}
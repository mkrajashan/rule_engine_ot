<?php
namespace App\Service;

use App\Entity\Upload;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

class RuleEvaluator
{
    private const HIGH_SEVERITY_THRESHOLD = 7.0;
    private const CRITICAL_VULN_COUNT = 5;

    public function __construct(
        private MailerInterface $mailer,
        private NotifierInterface $notifier,
        private LoggerInterface $logger,
        private string $adminEmail
    ) {}

    public function evaluate(Upload $upload): void
    {
        $scanResult = $upload->getScanResult();
        
        /*
        Check for failed upload first
        */
        $this->logger->info('Check for failed upload first');
        
        if ($upload->getStatus() === 'failed') {
            $this->handleFailedUpload($upload);
            return;
        }

        /*
        Process automation rules if available
        */
        if (isset($scanResult['automationRules'])) {
            $this->processAutomationRules($scanResult['automationRules']);
        }

        /*
        Global vulnerability checks (fallback)
        */
        $vulnCount = $scanResult['vulnerabilitiesFound'] ?? 0;
        if ($vulnCount > 0) {
            $this->handleVulnerabilities($upload, $vulnCount);
        }
    }

    private function processAutomationRules(array $automationRules): void
    {
        $this->logger->info('Process Automation Rules');
        foreach ($automationRules as $rule) {
            if ($rule['hasCves'] && $rule['triggered']) {
                $this->handleTriggeredRule($rule);
            }
        }
    }

    private function handleTriggeredRule(array $rule): void
    {
        $this->logger->info('High Severity Rule');
        $highSeverityEvents = array_filter(
            $rule['triggerEvents'],
            fn($event) => ($event['cvss3'] ?? 0) >= self::HIGH_SEVERITY_THRESHOLD
        );

        if (in_array('sendEmail', $rule['ruleActions'])) {
            $this->logger->info('Rule Actions - Send Email');
            $this->sendRuleEmailAlert($rule, count($highSeverityEvents));
        }

        if (in_array('warnPipeline', $rule['ruleActions'])) {
            $this->logger->info('Rule Actions - warnPipeline - Slack');
            $this->sendPipelineWarning($rule);
        }
    }

    private function handleVulnerabilities(Upload $upload, int $vulnCount): void
    {
        $scanResult = $upload->getScanResult();
        $isCritical = $vulnCount >= self::CRITICAL_VULN_COUNT;

        $subject = sprintf(
            '%s: %d vulnerabilities found',
            $isCritical ? ' CRITICAL ALERT' : ' Security Warning',
            $vulnCount
        );

        // Email Notification
        $email = (new Email())
            ->from($this->adminEmail)
            ->to($this->adminEmail)
            ->subject($subject)
            ->text(sprintf(
                "Scan found %d vulnerabilities.\n\nDetails: %s",
                $vulnCount,
                $scanResult['detailsUrl'] ?? 'No details available'
            ));

        $this->mailer->send($email);

        // Slack Notification
        if ($isCritical) {
            $notification = (new Notification($subject, ['chat/slack']))
                ->content(sprintf(
                    "%s\nVulnerabilities: %d\nView details: %s",
                    $subject,
                    $vulnCount,
                    $scanResult['detailsUrl'] ?? ''
                ));

            $this->notifier->send($notification);
        }

        $this->logger->alert($subject, [
            'uploadId' => $upload->getId(),
            'vulnerabilities' => $vulnCount
        ]);
    }

    private function handleFailedUpload(Upload $upload): void
    {
        $subject = 'Upload Failed: ' . $upload->getFilename();
        $error = 'Unknown error';

        // Email Notification
        $email = (new Email())
            ->from($this->adminEmail)
            ->to($this->adminEmail)
            ->subject($subject)
            ->text(sprintf(
                "Upload of %s failed with error: %s",
                $upload->getFilename(),
                $error
            ));

        $this->mailer->send($email);

        // Slack Notification
        $notification = (new Notification($subject, ['chat/slack']))
            ->content(sprintf(
                "Upload failed for %s\nError: %s",
                $upload->getFilename(),
                $error
            ));

        $this->notifier->send($notification);

        $this->logger->error($subject, [
            'uploadId' => $upload->getId(),
            'error' => $error
        ]);
    }

    private function sendRuleEmailAlert(array $rule, int $highSeverityCount): void
    {
        $subject = sprintf(
            '%s %s: %d High Severity Vulnerabilities',
            $rule['triggered'] ? 'Triggered' : '⚠️ Warning',
            $rule['ruleDescription'],
            $highSeverityCount
        );

        $email = (new Email())
            ->from($this->adminEmail)
            ->to($this->adminEmail)
            ->subject($subject)
            ->html($this->renderRuleEmail($rule));

        $this->mailer->send($email);
    }

    private function sendPipelineWarning(array $rule): void
    {
        $notification = (new Notification(
            'Pipeline Warning: ' . $rule['ruleDescription'],
            ['chat/slack']
        ))->content($this->renderSlackMessage($rule));

        $this->notifier->send($notification);
    }

    private function renderRuleEmail(array $rule): string
    {
        $html = "<h2>{$rule['ruleDescription']}</h2>";
        $html .= "<p><strong>Rule:</strong> <a href='{$rule['ruleLink']}'>View in Debricked</a></p>";
        $html .= "<h3>Triggered Events:</h3><ul>";

        foreach ($rule['triggerEvents'] as $event) {
            $severity = ($event['cvss3'] ?? 0) >= self::HIGH_SEVERITY_THRESHOLD ? 'HIGH' : 'medium';
            $html .= sprintf(
                '<li>[%s] <a href="%s">%s</a> (CVSS3: %.1f) in %s</li>',
                $severity,
                $event['cveLink'],
                $event['cve'],
                $event['cvss3'] ?? 0,
                $event['dependency']
            );
        }

        $html .= "</ul>";
        return $html;
    }

    private function renderSlackMessage(array $rule): string
    {
        $message = "*{$rule['ruleDescription']}*\n";
        $message .= "<{$rule['ruleLink']}|View Rule>\n";
        $message .= "*Triggered Events:*\n";

        foreach ($rule['triggerEvents'] as $event) {
            $message .= sprintf(
                "- <%s|%s> (%.1f) in %s\n",
                $event['cveLink'],
                $event['cve'],
                $event['cvss3'] ?? 0,
                $event['dependency']
            );
        }

        return $message;
    }
}
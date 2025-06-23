<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\Upload;
use Doctrine\ORM\EntityManagerInterface;

use App\Message\ScanUploadMessage;
use Symfony\Component\Messenger\MessageBusInterface;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\DataPart;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles the uploading of dependency files to Debricked's API
 * and dispatches scan initiation messages via Symfony Messenger.
 *
 * @package App\Service
 */
class DebrickedUploader
{
    private const API_URL = 'https://debricked.com/api/1.0/open/';

    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger
    ) {
        $this->client = HttpClient::create();
        $this->cache = $cache;
    }

    public function getToken()
    {
        
        $item = $this->cache->getItem('debricked_api_token');
        $token = $item->isHit() ? $item->get() : null;
        $this->logger->info('Get Token from Cache'.$token);
        return $token;
    }

    public function getUploadedBy()
    {
        $uploadedByItem = $this->cache->getItem('uploaded_by');
        $uploadedBy = $uploadedByItem->isHit() ? $uploadedByItem->get() : null;
        $this->logger->info('Get Uploaded_By from Cache'.$uploadedBy);
        return $uploadedBy;        
    }
    public function uploadDependencyFiles(array $files): array
    {
        $results = [];
        $temp = [];
        $uploadId = '';

        foreach ($files as $file) {
            $orignalFileName = $file->getClientOriginalName();
            if(!$this->checkSupportedFormat($orignalFileName)) {
                $this->logger->error('Dependency File not Supported'.$orignalFileName);
                $results = ['orignalFileName' => $orignalFileName, 'message' => 'Unsupported file format', 'statusCode' => 404];
                return $results;
            }
        }

        foreach ($files as $file) {
            try {
                $orignalFileName = $file->getClientOriginalName();
                $token = $this->getToken();
                
                $formFields = [
                    'commitName'         => 'c3184e118ca9387fa2c8a9d469498226d39fe5b8',
                    'repositoryUrl'      => '',
                    'fileData'           => DataPart::fromPath($file->getRealPath(), $file->getClientOriginalName()),
                    'fileRelativePath'   => '',
                    'branchName'         => '',
                    'defaultBranchName'  => '',
                    'releaseName'        => '',
                    'repositoryName'     => 'https://github.com/mkrajashan/rule-engine',
                    'productName'        => '',
                ];

                if(!empty($preUploadId)) {
                    $formFields['ciUploadId'] = (string) $preUploadId;
                }
                $this->logger->info('Form Data'.json_encode($formFields));
                $formData = new FormDataPart($formFields);

                $headers = $formData->getPreparedHeaders()->toArray();
                $headers[] = 'Authorization: Bearer ' . $token;
                $headers[] = 'accept: */*';
                $response = $this->client->request('POST', self::API_URL . 'uploads/dependencies/files', [
                    'headers' => $headers,
                    'body'    => $formData->bodyToIterable(),
                ]);

                $responseContent = $response->getContent(false);
                $data = $response->toArray();
                $uploadId = $data['ciUploadId'];
                $preUploadId = $uploadId;
                $this->logger->info('Pre Uplaoded Id '.$preUploadId);
                if (!$uploadId) {
                    throw new \Exception('Upload ID not received');
                }

                $temp[] = ['orignalFileName' => $orignalFileName, 'status' =>  'Allowed & Scan completed'];

            } catch (\Exception $e) {
                return [$e->getMessage()];
            }
        }

        $results = ['uploadedId' => $uploadId, 'details' => $temp, 'statusCode' => 200]; 
        return $results;
    }

    public function scanFiles($uploadId) {
        try {
            $token = $this->getToken();

            $formFields = [
                'commitName'         => 'c3184e118ca9387fa2c8a9d469498226d39fe5b8',
                'repositoryUrl'      => '',
                'ciUploadId'         => (string) $uploadId,
                'experimental'       => 'free',
                'repositoryName'     => 'https://github.com/mkrajashan/rule-engine',
                'returnCommitData'     => 'true',
            ];
    
            $formData = new FormDataPart($formFields);
            $headers = $formData->getPreparedHeaders()->toArray();
            $headers[] = 'Authorization: Bearer ' . $token;
            $headers[] = 'accept: */*';

            $response = $this->client->request('POST', self::API_URL . 'finishes/dependencies/files/uploads', [
                'headers' => $headers,
                'body'    => $formData->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $this->logger->info('Scan Files Response '.$content);
            
            $data = $response->toArray();
            $upload = new Upload();
            $upload->setStatus('completed');
            $upload->setScanResult($data);
            $upload->setUploadedAt(new \DateTime());            
            //$uploadedBy = $this->getUploadedBy();
            $uploadedBy = $this->getUploadedBy();
            $upload->setUploadedBy($uploadedBy);

            $this->em->persist($upload);
            $this->em->flush();

            if (!$uploadId) {
                throw new \Exception('Upload ID not received');
            }

            return $data;
        } catch (\Exception $e) {
            return 'Scan failed: ' . $e->getMessage();
        }
    }

    public function checkStatusAndTrigger($uploadId) {
        try {
            $token = $this->getToken();

            $statusUrl = self::API_URL . "ci/upload/status?ciUploadId=".$uploadId;
            $maxAttempts = 10;
            $attempt = 0;

            sleep(2);
            $this->logger->info('Check Status Started');
            $statusResp = $this->client->request('GET', $statusUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            $scanContent = $statusResp->getContent(false); 
            $this->logger->info('Check Status Completed'.$scanContent);
            $scanstatusData = json_decode($scanContent, true);

            if ($scanstatusData['progress'] === 100) {
                $upload = new Upload();
                $upload->setCiUploadId($uploadId);
                $upload->setStatus('completed');
                $upload->setScanResult($scanstatusData);
                $upload->setUploadedAt(new \DateTime());
                $this->em->persist($upload);
                $this->em->flush();
                $this->logger->info('Dispatch Handler after scan started');
                $this->bus->dispatch(new ScanUploadMessage(
                    uploadId: $upload->getId(),
                    ciUploadId: $uploadId,
                ));        
            } else {
                $this->logger->info('Retry until we get scan progress complete ');
                $this->checkStatusAndTrigger($uploadId);
            }
            return 'completed';
        } catch (\Exception $e) {
            $this->logger->error('checkStatusAndTrigger');
            return 'Scan failed: ' . $e->getMessage();
        }
    }

    public function checkSupportedFormat(string $orignalFileName): bool {
        try {
            $supportedFormats = [];
            $token = $this->getToken();
            if (!$token) {
                return false;
            }
            $this->logger->info('Check the supported file format'.$orignalFileName);
            $response = $this->client->request('GET', 'https://debricked.com/api/1.0/open/files/supported-formats', [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => 'Bearer ' . $token,
                ]
            ]);
            
            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            //if ($statusCode !== 200 || !is_array($data)) {                
            if ($statusCode !== 200) {                
                return false;
            }
        
            foreach ($data as $entry) {
                if (!empty($entry['regex'])) {
                    $supportedFormats[] = $entry['regex'];
                }
            
                foreach ($entry['lockFileRegexes'] as $lockRegex) {
                    $supportedFormats[] = $lockRegex;
                }
            }

            foreach ($supportedFormats as $regex) {
                if (@preg_match('/' . $regex . '/i', $orignalFileName)) {
                    return true;
                }
            }
            return false;

        } catch (\Exception $e) {
            return false;
        }
    }
}
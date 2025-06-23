<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\DebrickedUploader;
use Psr\Log\LoggerInterface;

class UploadController extends AbstractController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Route('/api/upload', methods: ['POST'])]
    public function upload(Request $request, DebrickedUploader $uploader): JsonResponse
    {
        $files = $request->files->get('files');
        $scanRData = [];
        if (empty($files)) {
            throw new BadRequestHttpException('No files uploaded.');
        }

        try {
            $uploadResponse = $uploader->uploadDependencyFiles($files);
            if(array_key_exists('statusCode', $uploadResponse) && $uploadResponse['statusCode'] == 404) {
                $this->logger->error('Dependency File not supported:- '.$uploadResponse['orignalFileName']);
                return $this->json(['status' => 'success', 'data' => $uploadResponse['message'], 'filename' => $uploadResponse['orignalFileName']]);
            }
            
            if(array_key_exists('statusCode', $uploadResponse) && $uploadResponse['statusCode'] == 200) {
                $ciUploadid =  $uploadResponse['uploadedId'];
                $scanResult = $uploader->scanFiles($ciUploadid);
                $scanRData = $uploader->checkStatusAndTrigger($ciUploadid);                       
            }

            return $this->json(['status' => 'success', 'data' => $scanRData]); 

        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}

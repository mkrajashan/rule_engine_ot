<?php

namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class LoginController extends AbstractController{

    CONST DEBRICK_LOGIN_CHECK_URL  = 'https://debricked.com/api/login_check';
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache, private LoggerInterface $logger)
    {
        $this->cache = $cache;
    }

    #[Route('/api/login-check', name:'app-login-check', methods:['post'])]
    public function loginCheck(Request $request):JsonResponse {

        $userInfo = json_decode($request->getContent(), true);

        $this->logger->info('Login Request'.$request->getContent());

        $userName = $userInfo['username'] ?? NULL;
        $passWord = $userInfo['password'] ?? NULL;

        $httpClient = HttpClient::create();

        try{
            $response = $httpClient->request('POST', self::DEBRICK_LOGIN_CHECK_URL, ['headers' => 
                [
                    'Content-Type' => 'application/json',
                    'Accept' => '*/*'
                ],
                'json' => [
                    '_username' => $userName,
                    '_password' => $passWord,
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $responseContent = $response->getContent();
            $data = $response->toArray(false);
            
            if ($statusCode !== 200 || !isset($data['token'])) {
                return $this->json(['error' => 'Debricked authentication failed'], 401);
            }

            $item = $this->cache->getItem('debricked_api_token');
            $item->set($data['token']);
                        
            $this->cache->save($item);

            $uploadedBy = $this->cache->getItem('uploaded_by');
            $uploadedBy->set($userName);
                        
            $this->cache->save($uploadedBy);
            $this->logger->info('Login Response'.$responseContent);
            return $this->json(['token' => $data['token'],'status' => 200]);
                    
        } catch (\Exception $e) {
            $this->logger->error('Login Error'.$e->getMessage());
            return $this->json(['error' => 'Authorization failed: ' . $e->getMessage()], 500);
        }
    }
}
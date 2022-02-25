<?php

namespace Conduction\CommonGroundBundle\Service;

use DateTime;
use GuzzleHttp\Client;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthenticationService
{
    private ParameterBagInterface $parameterBag;
    private FileService $fileService;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        $this->fileService = new FileService();
    }

    public function convertRSAtoJWK(array $component): JWK
    {
        if (key_exists('privateKey', $component)) {
            $rsa = base64_decode($component['privateKey']);
        } else {
            $rsa = base64_decode($this->parameterBag->get('jwt.privateKey'));
        }
        $filename = $this->fileService->writeFile('privateKey', $rsa);
        $jwk = JWKFactory::createFromKeyFile(
            $filename,
            null,
            [
                'use' => 'sig',
            ]
        );
        $this->fileService->removeFile($filename);
    }

    public function getAlgorithm(array $component): string
    {
        if ($component['auth'] == 'jwt-HS256' || $component['auth'] == 'jwt') {
            return 'HS256';
        } else {
            return 'RS512';
        }
    }

    public function getJWK(string $algorithm, $component): JWK
    {
        if ($algorithm == 'HS256') {
            return new JWK([
                'kty' => 'oct',
                'k'   => base64_encode(addslashes($component['secret'])),
            ]);
        } else {
            return $this->convertRSAtoJWK($component);
        }
    }

    public function getApplicationId(array $component): string
    {
        if (key_exists('id', $component)) {
            return $component['id'];
        }

        return $this->parameterBag->get('jwt.id');
    }

    public function getJwtPayload(array $component): string
    {
        $now = new DateTime('now');
        $clientId = $this->getApplicationId($component);

        return json_encode([
            'iss'                => $clientId,
            'iat'                => $now->getTimestamp(),
            'client_id'          => $clientId,
            'user_id'            => $this->parameterBag->get('app_name'),
            'user_representation' => $this->parameterBag->get('app_name'),
        ]);
    }

    /**
     * Create a JWT token from Component settings.
     *
     * @param array $component The code of the component
     * @param string The JWT token
     */
    public function getJwtToken(array $component): string
    {
        $algorithmManager = new AlgorithmManager([new HS256(), new RS512()]);
        $algorithm = $this->getAlgorithm($component);
        $jwsBuilder = new JWSBuilder($algorithmManager);

        $jwk = $this->getJWK($algorithm, $component);
        $clientId = $this->getApplicationId($component);
        $payload = $this->getJwtPayload($component);

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => $algorithm])
            ->build();

        $jwsSerializer = new CompactSerializer();

        return $jwsSerializer->serialize($jws, 0);
    }

    public function getTokenFromUrl(array $component): string
    {
        $guzzleConfig = [
            // Base URI is used with relative requests
            'http_errors' => false,
            // You can set any number of default request options.
            'timeout' => 4000.0,
            // To work with NLX we need a couple of default headers
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            // Do not check certificates
            'verify' => false,
            'auth' => [$component['username'], $component['password']],
        ];
        if ($this->parameterBag->has('app_certificate') && file_exists($this->parameterBag->get('app_certificate'))) {
            $guzzleConfig['cert'] = $this->parameterBag->get('app_certificate');
        }
        if ($this->parameterBag->has('app_ssl_key') && file_exists($this->parameterBag->get('app_ssl_key'))) {
            $guzzleConfig['ssl_key'] = $this->parameterBag->get('app_ssl_key');
        }
        $client = new Client($guzzleConfig);

        $response = $client->post($component['location'] . '/oauth/token', ['form_params' => ['grant_type' => 'client_credentials', 'scope' => 'api']]);
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['access_token'];
    }
    
    public function getHmacToken(array $requestOptions, array $component): string
    {
        // todo: what if we don't have a body, method or url in $requestOptions?
        
        switch ($requestOptions['method']) {
            case "POST":
                $post = json_encode($requestOptions['body']);
    
                $md5  = md5($post, true);
                $post = base64_encode($md5);
                break;
            case "GET":
            default:
                // todo: what about a get call?
                $get = 'not a UTF-8 string';
                $post = base64_encode($get);
                break;
        }
    
        $websiteKey = $component['apikey'];
        $uri        = strtolower(urlencode($requestOptions['url']));
        $nonce      = 'nonce_' . rand(0000000, 9999999);
        $time       = time();
    
        $hmac       = $websiteKey . $requestOptions['method'] . $uri . $time . $nonce . $post;
        $s          = hash_hmac('sha256', $hmac, $component['secret'], true);
        $hmac       = base64_encode($s);
        
        return "hmac " . $websiteKey . ':' . $hmac . ':' . $nonce . ':' . $time;
    }

    public function setAuthorization(array $requestOptions, ?array $component = []): array
    {
        if ($component && array_key_exists('auth', $component)) {
            switch ($component['auth']) {
                case 'jwt-HS256':
                case 'jwt-RS512':
                case 'jwt':
                    $requestOptions['headers']['Authorization'] = 'Bearer ' . $this->getJwtToken($component);
                    break;
                case 'username-password':
                    $requestOptions['auth'] = [$component['username'], $component['password']];
                    break;
                case 'vrijbrp-jwt':
                    $requestOptions['headers']['Authorization'] = "Bearer {$this->getTokenFromUrl($component)}";
                    break;
                case 'hmac':
                    $requestOptions['headers']['Authorization'] = $this->getHmacToken($requestOptions, $component);
                  break;
                case 'apikey':
                    if (array_key_exists('authorizationHeader', $component) && array_key_exists('passthroughMethod', $component)) {
                        switch ($component['passthroughMethod']) {
                            case 'query':
                                $requestOptions['query'][$component['authorizationHeader']] = $component['apikey'];
                                break;
                            default:
                                $requestOptions['headers'][$component['authorizationHeader']] = $component['apikey'];
                                break;
                        }
                    } else {
                        $requestOptions['headers']['Authorization'] = $component['apikey'];
                    }
                    break;
                default:
                    break;
            }
        } else {
            $requestOptions['headers']['Authorization'] = $component['apikey'];
        }

        return $requestOptions;
    }

    /**
     * @param string $token     The token to verify
     * @param string $publicKey The public key to verify the token to
     *
     * @throws HttpException Thrown when the token cannot be verified
     *
     * @return array The payload of the token
     */
    public function verifyJWTToken(string $token, string $publicKey): array
    {
        $algorithmManager = new AlgorithmManager([new HS256(), new RS512()]);
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $publicKeyFile = $this->fileService->writeFile('publickey', $publicKey);
        $jwk = JWKFactory::createFromKeyFile($publicKeyFile, null, []);

        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);

        $jws = $serializerManager->unserialize($token);
        if ($jwsVerifier->verifyWithKey($jws, $jwk, 0)) {
            $this->fileService->removeFile($publicKeyFile);

            return json_decode($jws->getPayload(), true);
        } else {
            throw new AuthenticationException('Unauthorized: The provided Authorization header is invalid', 401);
        }
    }
}

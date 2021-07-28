<?php

namespace Conduction\CommonGroundBundle\Service;

use DateTime;
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
            'user_representation'=> $this->parameterBag->get('app_name'),
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

    public function setAuthorization(array $requestOptions, ?array $component = []): array
    {
        if ($component && array_key_exists('auth', $component)) {
            switch ($component['auth']) {
                case 'jwt-HS256':
                case 'jwt-RS512':
                case 'jwt':
                    $requestOptions['headers']['Authorization'] = 'Bearer '.$this->getJwtToken($component);
                    break;
                case 'username-password':
                    $requestOptions['auth'] = [$component['username'], $component['password']];
                    break;
                case 'apikey':
                default:
                    $requestOptions['headers']['Authorization'] = $component['apikey'];
                    break;
            }
        } else {
            $requestOptions['headers']['Authorization'] = $component['apikey'];
        }

        return $requestOptions;
    }

    /**
     * @param string $token The token to verify
     * @param string $publicKey The public key to verify the token to
     * @return array The payload of the token
     * @throws HttpException Thrown when the token cannot be verified
     */
    public function verifyJWTToken(string $token, string $publicKey): array
    {
        $algorithmManager = new AlgorithmManager([new HS256(), new RS512()]);
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $publicKeyFile = $this->fileService->writeFile('publickey', base64_decode($publicKey));
        $jwk = JWKFactory::createFromKeyFile($publicKeyFile, null, []);

        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);

        $jws = $serializerManager->unserialize($token);
        if($jwsVerifier->verifyWithKey($jws, $jwk, 0)){
            $this->fileService->removeFile($publicKeyFile);
            return json_decode($jws->getPayload(), true);
        } else {
            throw new AuthenticationException("Unauthorized: The provided Authorization header is invalid", 401);
        }
    }
}

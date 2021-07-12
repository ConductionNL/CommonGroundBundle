<?php


namespace Conduction\CommonGroundBundle\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AuthenticationService
{
    private ParameterBagInterface $parameterBag;
    private FileService $fileService;

    public function __construct(ParameterBagInterface $parameterBag){
        $this->parameterBag = $parameterBag;
        $this->fileService = new FileService();
    }

    public function convertRSAtoJWK(array $component): JWK
    {
        if(key_exists('privateKey', $component)){
            $rsa = base64_decode($component['privateKey']);
        } else {
            $rsa = base64_decode($this->parameterBag->get('jwt.privateKey'));
        }
        $filename = $this->fileService->writeFile('privateKey', $rsa);
        $jwk = new JWKFactory::createFromKeyFile(
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
        if($component['auth'] == 'jwt-HS256' || $component['auth'] == 'jwt'){
            return 'HS256';
        } else {
            return 'RS512';
        }
    }

    public function getJWK(string $algorithm, $component): JWK
    {
        if($algorithm == 'HS256'){
            return new JWK([
                'kty' => 'oct',
                'k' => base64_encode(addslashes($component['secret'])),
            ]);
        } else {
            return $this->convertRSAtoJWK($component);
        }
    }

    public function getApplicationId(array $component): string
    {
        if(key_exists('id', $component)){
            return $component['id'];
        }
        return $this->parameterBag->get('jwt.id');
    }

    public function getJwtPayload(array $component): array
    {
        $now = new DateTime('now');
        $clientId = $this->getApplicationId($component);
        return json_encode([
            'iss'                => $clientId,
            'iat'                => $now->getTimestamp(),
            'client_id'          => $clientId,
            'user_id'            => $this->params->get('app_name'),
            'user_representation'=> $this->params->get('app_name'),
        ]);
    }

    /**
    * Create a JWT token from Component settings
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
                    $requestOptions['headers']['Authorization'] = 'Bearer '.$this->getJwtToken($component['code']);
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
}
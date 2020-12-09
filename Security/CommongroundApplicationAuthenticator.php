<?php

// src/Security/TokenAuthenticator.php

namespace Conduction\CommonGroundBundle\Security;

use App\Entity\Application;
use Conduction\CommonGroundBundle\Security\User\CommongroundApplication;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class CommongroundApplicationAuthenticator extends AbstractGuardAuthenticator
{
    private EntityManagerInterface $em;
    private ParameterBagInterface $params;
    private CommonGroundService $commonGroundService;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params, CommonGroundService $commonGroundService)
    {
        $this->em = $em;
        $this->params = $params;
        $this->commonGroundService = $commonGroundService;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        return $request->headers->has('Authorization');
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        return [
            'token' => $request->headers->get('Authorization'),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $apiToken = $credentials['token'];


        if ($apiToken != $this->params->get('app_application_key') && $this->params->get('app_auth') != 'true') {
            return;
        }
        elseif($this->params->get('app_auth') == 'true' && !$this->validateJWT($apiToken)){
            return;
        }

        // Make the actual api call for the user
        //return $this->em->getRepository(CommongroundUser::class)
        //->findOneBy(['apiToken' => $apiToken]);

        $user = new CommongroundApplication('Default Application', $apiToken, '', null, ['user']);

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // check credentials - e.g. make sure the password is valid
        // no credential check is needed in this case

        // return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            // you might translate this message
            'message' => 'Authentication Required',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }

    public function validateJWT(string $bearer): bool
    {

        if(strpos($bearer, 'Bearer ') === false){
            return false;
        }
        $jwt = str_replace('Bearer ', '', $bearer);

        $jwsSerializer = new CompactSerializer();
        $jwt = $jwsSerializer->unserialize($jwt);

        $algorithmManager = new AlgorithmManager([new RS512()]);

        $applications = $this->commonGroundService->getResourceList(['component' => 'ac', 'type' => 'applications'])['hydra:member'];

        $payload = json_decode($jwt->getPayload(), true);
        $clientId = $payload['client_id'];

        foreach($applications as $application){
            if(in_array($clientId, $application['clientIds'])){
                break;
            }
        }

        $public = JWKFactory::createFromValues($application->getPublicKey());

        $creation = date_timestamp_set(new \DateTime(), $payload['iat']);
        $maxAge = new \DateTime("now - 1 hour");

        $jwsVerifier = new JWSVerifier(
            $algorithmManager
        );


        if($jwsVerifier->verifyWithKey($jwt, $public, 0) && $creation > $maxAge && $application->getHasAllAuthorizations()){
            return true;
        }
        return false;
    }
}

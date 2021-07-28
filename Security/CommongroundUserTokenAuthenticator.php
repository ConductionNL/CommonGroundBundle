<?php

// src/Security/TokenAuthenticator.php

/*
 * This authenticator authenticas agains the commonground user component
 *
 */

namespace Conduction\CommonGroundBundle\Security;

use Conduction\CommonGroundBundle\Security\User\CommongroundUser;
use Conduction\CommonGroundBundle\Service\AuthenticationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

class CommongroundUserTokenAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var FlashBagInterface
     */
    private ParameterBagInterface $parameterBag;
    private CommonGroundService $commonGroundService;
    private AuthenticationService $authenticationService;

    public function __construct(ParameterBagInterface $parameterBag, CommonGroundService $commonGroundService)
    {
        $this->parameterBag = $parameterBag;
        $this->commonGroundService = $commonGroundService;
        $this->authenticationService = new AuthenticationService($parameterBag);
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        return $request->headers->has('Authorization') && strpos($request->headers->get('Authorization'), 'Bearer') !== false;
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        return [
            'token' => substr($request->headers->get('Authorization'), strlen('Bearer ')),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $payload = $this->authenticationService->verifyJWTToken($credentials['token'], $this->parameterBag->get('public_key'));

        $user = $this->commonGroundService->getResource(['component'=>'uc', 'type'=>'users', 'id' => $payload['userId']], [], true, false, true, false, false);

        if ($user['username'] != $payload['username']) {
            throw new AuthenticationException('The provided token does not match the user it refers to');
        }

        if (!in_array('ROLE_USER', $user['roles'])) {
            $user['roles'][] = 'ROLE_USER';
        }
        foreach ($user['roles'] as $key=>$role) {
            if (strpos($role, 'ROLE_') !== 0) {
                $user['roles'][$key] = "ROLE_$role";
            }
        }
        if (isset($user['organization'])) {
            return new CommongroundUser($user['username'], $user['id'], $user['username'], null, $user['roles'], $user['person'], $user['organization'], 'user');
        } else {
            return new CommongroundUser($user['username'], $user['id'], $user['username'], null, $user['roles'], $user['person'], null, 'user');
        }
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // no adtional credential check is needed in this case so return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
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
}

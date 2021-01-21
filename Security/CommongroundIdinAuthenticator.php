<?php

// src/Security/TokenAuthenticator.php

/*
 * This authenticator authenticates against DigiSpoof
 *
 */

namespace Conduction\CommonGroundBundle\Security;

use Conduction\CommonGroundBundle\Security\User\CommongroundUser;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class CommongroundIdinAuthenticator extends AbstractGuardAuthenticator
{
    private $em;
    private $params;
    private $commonGroundService;
    private $csrfTokenManager;
    private $router;
    private $urlGenerator;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params, CommonGroundService $commonGroundService, CsrfTokenManagerInterface $csrfTokenManager, RouterInterface $router, UrlGeneratorInterface $urlGenerator, SessionInterface $session)
    {
        $this->em = $em;
        $this->params = $params;
        $this->commonGroundService = $commonGroundService;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->router = $router;
        $this->urlGenerator = $urlGenerator;
        $this->session = $session;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        return 'app_user_idin' === $request->attributes->get('_route')
            && $request->isMethod('GET') && $request->query->get('code');
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        $code = $request->query->get('code');

        $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'idin', 'application' => $this->params->get('app_id')])['hydra:member'];
        $provider = $provider[0];

        $endpoint = str_replace('/oidc/token', '', $provider['configuration']['endpoint']);

        $redirect = str_replace('http:', 'https:', $request->getUri());
        $redirect = substr($redirect, 0, strpos($redirect, '?'));

        $body = [
            'client_id'    => $provider['configuration']['app_id'],
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirect,
        ];

        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $endpoint,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);

        $response = $client->request('POST', '/oidc/token', [
            'auth'        => [$provider['configuration']['app_id'], $provider['configuration']['secret']],
            'form_params' => $body,
        ]);

        $token = json_decode($response->getBody()->getContents(), true);

        $headers = [
            'Authorization' => 'Bearer '.$token['access_token'],
            'Accept'        => 'application/json',
        ];

        $response = $client->request('GET', '/oidc/userinfo', [
            'headers' => $headers,
        ]);

        $user = json_decode($response->getBody()->getContents(), true);

        $credentials = [
            'username'    => $user['consumer.bin'],
            'firstName'   => $user['consumer.initials'],
            'lastName'    => $user['consumer.legallastname'],
        ];

        if (isset($user['consumer.email'])) {
            $credentials['email'] = $user['consumer.email'];
        }

        if (isset($user['consumer.telephone'])) {
            $credentials['telephone'] = $user['consumer.telephone'];
        }

        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['username']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $application = $this->commonGroundService->cleanUrl(['component'=>'wrc', 'type'=>'applications', 'id'=>$this->params->get('app_id')]);
        $providers = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'idin', 'application' =>$this->params->get('app_id')])['hydra:member'];
        $tokens = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $credentials['username'], 'provider.name' => $providers[0]['name']])['hydra:member'];

        if (!$tokens || count($tokens) < 1) {
            $users = $this->commonGroundService->getResourceList(['component'=>'uc', 'type'=>'users'], ['username'=> $credentials['username']], true, false, true, false, false);
            $users = $users['hydra:member'];

            if (count($users) < 1) {
                //create person
                $person = [];
                $person['name'] = $credentials['firstName'];
                $person['givenName'] = $credentials['firstName'];
                $person['familyName'] = $credentials['lastName'];

                if (isset($credentials['email'])) {
                    //create email
                    $person['emails'][0]['name'] = $credentials['email'];
                    $person['emails'][0]['email'] = $credentials['email'];
                }

                if (isset($credentials['telephone'])) {
                    //create phoneNumber
                    $person['telephones'][0]['name'] = $credentials['telephone'];
                    $person['telephones'][0]['telephone'] = $credentials['telephone'];
                }

                $person = $this->commonGroundService->createResource($person, ['component' => 'cc', 'type' => 'people']);

                //create user
                $user = [];
                $user['username'] = $credentials['username'];
                $user['password'] = $credentials['username'];
                $user['person'] = $person['@id'];
                $user = $this->commonGroundService->createResource($user, ['component' => 'uc', 'type' => 'users']);
            } else {
                $user = $users[0];
            }

            //create token
            $token = [];
            $token['token'] = $credentials['username'];
            $token['user'] = 'users/'.$user['id'];
            $token['provider'] = 'providers/'.$providers[0]['id'];
            $token = $this->commonGroundService->createResource($token, ['component' => 'uc', 'type' => 'tokens']);

            $token = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $credentials['username'], 'provider.name' => $providers[0]['name']])['hydra:member'];
        } else {
            $token = $tokens[0];
            // Deze $urls zijn een hotfix voor niet werkende @id's op de cgb cgs
            $userUlr = $this->commonGroundService->cleanUrl(['component'=>'uc', 'type'=>'users', 'id'=>$token['user']['id']]);
            $user = $this->commonGroundService->getResource($userUlr);
        }

        $person = $this->commonGroundService->getResource($user['person']);

        $log = [];
        $log['address'] = $_SERVER['REMOTE_ADDR'];
        $log['method'] = 'Idin-Ident';
        $log['status'] = '200';
        $log['application'] = $application;

        $this->commonGroundService->saveResource($log, ['component' => 'uc', 'type' => 'login_logs']);

        if (!in_array('ROLE_USER', $user['roles'])) {
            $user['roles'][] = 'ROLE_USER';
        }
        array_push($user['roles'], 'scope.chin.checkins.read');

        if (isset($user['organization'])) {
            return new CommongroundUser($user['username'], $user['username'], $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'idin');
        } else {
            return new CommongroundUser($user['username'], $user['username'], $person['name'], null, $user['roles'], $user['person'], null, 'idin');
        }
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        $application = $this->commonGroundService->cleanUrl(['component'=>'wrc', 'type'=>'applications', 'id'=>$this->params->get('app_id')]);
        $providers = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'idin', 'application' =>$this->params->get('app_id')])['hydra:member'];
        $token = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $credentials['username'], 'provider.name' => $providers[0]['name']])['hydra:member'];

        if (!$token || count($token) < 1) {
            return;
        }

        // no adtional credential check is needed in this case so return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $backUrl = $this->session->get('backUrl', false);

        $this->session->remove('backUrl');

        if ($backUrl) {
            $this->session->set('checkingProvider', 'idin-identity');

            return new RedirectResponse($backUrl);
        }
        //elseif(isset($application['defaultConfiguration']['configuration']['userPage'])){
        //    return new RedirectResponse('/'.$application['defaultConfiguration']['configuration']['userPage']);
        //}
        else {
            return new RedirectResponse($this->router->generate('app_default_index'));
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new RedirectResponse($this->router->generate('app_user_idin'));
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if ($this->params->get('app_subpath') && $this->params->get('app_subpath') != 'false') {
            return new RedirectResponse('/'.$this->params->get('app_subpath').$this->router->generate('app_user_digispoof', []));
        } else {
            return new RedirectResponse($this->router->generate('app_user_digispoof', [], UrlGeneratorInterface::RELATIVE_PATH));
        }
    }

    public function supportsRememberMe()
    {
        return true;
    }

    protected function getLoginUrl()
    {
        if ($this->params->get('app_subpath') && $this->params->get('app_subpath') != 'false') {
            return '/'.$this->params->get('app_subpath').$this->router->generate('app_user_digispoof', [], UrlGeneratorInterface::RELATIVE_PATH);
        } else {
            return $this->router->generate('app_user_digispoof', [], UrlGeneratorInterface::RELATIVE_PATH);
        }
    }
}

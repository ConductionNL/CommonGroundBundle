<?php

// src/Security/User/CommongroundUserProvider.php

namespace Conduction\CommonGroundBundle\Security\User;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CommongroundProvider implements UserProviderInterface
{
    private $params;
    private $commonGroundService;
    private $session;

    public function __construct(ParameterBagInterface $params, CommonGroundService $commonGroundService, SessionInterface $session)
    {
        $this->params = $params;
        $this->commonGroundService = $commonGroundService;
        $this->session = $session;
    }

    public function loadUserByUsername($username)
    {
        return $this->fetchUser($username);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof CommongroundUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        $username = $user->getUsername();
        $password = $user->getPassword();
        $organization = $user->getOrganization();
        $type = $user->getType();
        $person = $user->getPerson();
        $authorization = $user->getAuthorization();

        return $this->fetchUser($username, $password, $organization, $type, $person, $authorization);
    }

    public function supportsClass($class)
    {
        return CommongroundUser::class === $class;
    }

    private function fetchUser($username, $password, $organization, $type, $person, $authorization)
    {
        //only trigger if type of user is organization
        $application = $this->commonGroundService->cleanUrl(['component'=>'wrc', 'type'=>'applications', 'id'=>$this->params->get('app_id')]);
        if ($type == 'organization') {
            try {
                $kvk = $this->commonGroundService->getResource(['component'=>'kvk', 'type'=>'companies', 'id'=>$organization]);
            } catch (\HttpException $e) {
                return;
            }
            $user = $this->commonGroundService->getResource($person);
            if (!isset($user['roles'])) {
                $user['roles'] = [];
            }
        } elseif ($type == 'person') {
            $user = $this->commonGroundService->getResource($person);
            if (!isset($user['roles'])) {
                $user['roles'] = [];
            }
        } elseif ($type == 'user') {
            $users = $this->commonGroundService->getResourceList(['component'=>'uc', 'type'=>'users'], ['username'=> $username], true);
            $users = $users['hydra:member'];
            $user = $users[0];
        } elseif ($type == 'idin') {
            $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'idin', 'application' => $this->params->get('app_id')])['hydra:member'];
            $tokens = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $username, 'provider.name' => $provider[0]['name']])['hydra:member'];
            // Deze $urls zijn een hotfix voor niet werkende @id's op de cgb cgs
            $userUlr = $this->commonGroundService->cleanUrl(['component'=>'uc', 'type'=>'users', 'id'=>$tokens[0]['user']['id']]);
            $user = $this->commonGroundService->getResource($userUlr);
            if (!isset($user['roles'])) {
                $user['roles'] = [];
            }
        } elseif ($type == 'facebook') {
            $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'facebook', 'application' => $this->params->get('app_id')])['hydra:member'];
            $tokens = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $password, 'provider.name' => $provider[0]['name']])['hydra:member'];
            // Deze $urls zijn een hotfix voor niet werkende @id's op de cgb cgs
            $userUlr = $this->commonGroundService->cleanUrl(['component'=>'uc', 'type'=>'users', 'id'=>$tokens[0]['user']['id']]);
            $user = $this->commonGroundService->getResource($userUlr);
            if (!isset($user['roles'])) {
                $user['roles'] = [];
            }
        } elseif ($type == 'github') {
            $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'github', 'application' => $this->params->get('app_id')])['hydra:member'];
            $tokens = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $password, 'provider.name' => $provider[0]['name']])['hydra:member'];
            // Deze $urls zijn een hotfix voor niet werkende @id's op de cgb cgs
            $userUlr = $this->commonGroundService->cleanUrl(['component'=>'uc', 'type'=>'users', 'id'=>$tokens[0]['user']['id']]);
            $user = $this->commonGroundService->getResource($userUlr);
            if (!isset($user['roles'])) {
                $user['roles'] = [];
            }
        } elseif ($type == 'gmail') {
            $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'gmail', 'application' => $this->params->get('app_id')])['hydra:member'];
            $tokens = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $password, 'provider.name' => $provider[0]['name']])['hydra:member'];
            // Deze $urls zijn een hotfix voor niet werkende @id's op de cgb cgs
            $userUlr = $this->commonGroundService->cleanUrl(['component'=>'uc', 'type'=>'users', 'id'=>$tokens[0]['user']['id']]);
            $user = $this->commonGroundService->getResource($userUlr);
            if (!isset($user['roles'])) {
                $user['roles'] = [];
            }
        } elseif ($type == 'id-vault') {
            $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $this->params->get('app_id')])['hydra:member'];
            $tokens = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'tokens'], ['token' => $password, 'provider.name' => $provider[0]['name']])['hydra:member'];
            // Deze $urls zijn een hotfix voor niet werkende @id's op de cgb cgs
            $userUlr = $this->commonGroundService->cleanUrl(['component'=>'uc', 'type'=>'users', 'id'=>$tokens[0]['user']['id']]);
            $user = $this->commonGroundService->getResource($userUlr);
            if (!isset($user['roles'])) {
                $user['roles'] = [];
            }
        }

        if (!isset($user['roles'])) {
            $user['roles'] = [];
        }

        if (!in_array('ROLE_USER', $user['roles'])) {
            $user['roles'][] = 'ROLE_USER';
        }

        foreach ($user['roles'] as $key=>$role) {
            if (strpos($role, 'ROLE_') !== 0) {
                $user['roles'][$key] = "ROLE_$role";
            }
        }

        //We create a CommongroundUser based on user type.
        switch ($type) {
            case 'person':
                $resident = $this->checkResidence('person', $user, null);

                return new CommongroundUser($user['naam']['voornamen'].' '.$user['naam']['geslachtsnaam'], $user['id'], $user['naam']['voornamen'].' '.$user['naam']['geslachtsnaam'], null, $user['roles'], $user['@id'], null, 'person', $resident);
            case 'organization':
                $resident = $this->checkResidence('organization', $user, $kvk);

                return new CommongroundUser($kvk['name'], $user['id'], $kvk['name'], null, $user['roles'], $user['@id'], $kvk['id'], 'organization', $resident);
            case 'user':
                if (empty($user['person'])) {
                    throw new \Exception('This user has no person. ID: '.$user['id']);
                }
                $person = $this->commonGroundService->getResource($user['person']);

                if (isset($user['organization'])) {
                    return new CommongroundUser($user['username'], $user['id'], $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'user');
                } else {
                    return new CommongroundUser($user['username'], $user['id'], $person['name'], null, $user['roles'], $user['person'], null, 'user');
                }
            case 'idin':
                $person = $this->commonGroundService->getResource($user['person']);

                if (isset($user['organization'])) {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'idin');
                } else {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'idin');
                }
            case 'facebook':
                $person = $this->commonGroundService->getResource($user['person']);

                if (isset($user['organization'])) {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'facebook');
                } else {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'facebook');
                }
            case 'gmail':
                $person = $this->commonGroundService->getResource($user['person']);

                if (isset($user['organization'])) {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'gmail');
                } else {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'gmail');
                }
            case 'github':
                $person = $this->commonGroundService->getResource($user['person']);

                if (isset($user['organization'])) {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'github');
                } else {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'github');
                }
            case 'id-vault':
                $person = $this->commonGroundService->getResource($user['person']);
                if (isset($user['organization'])) {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'id-vault', false, $authorization);
                } else {
                    return new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'id-vault', false, $authorization);
                }
            default:
                throw new UsernameNotFoundException(
                    sprintf('User "%s" does not exist.', $username)
                );
        }
    }

    private function checkResidence($type, $user, $organization)
    {
        $application = $this->commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $this->params->get('app_id')]);
        $resident = false;
        if (isset($application['defaultConfiguration']['configuration']['cityNames'])) {
            foreach ($application['defaultConfiguration']['configuration']['cityNames'] as $name) {
                if ($type == 'person') {
                    if ($user['verblijfplaats']['woonplaatsnaam'] == $name) {
                        $resident = true;
                    }
                } elseif ($type == 'organization') {
                    if ($organization['addresses'][0]['city'] == $name) {
                        $resident = true;
                    }
                } else {
                    $resident = false;
                }
            }

            return $resident;
        } else {
            return false;
        }
    }
}

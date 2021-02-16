<?php

// src/Security/User/WebserviceUser.php

namespace Conduction\CommonGroundBundle\Security\User;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CommongroundUser implements UserInterface, EquatableInterface
{
    /* The username displayd */
    private $username;

    /* Provide UUID instead of normal password */
    private $password;

    /* The name of the user */
    private $name;

    /* Leave empty! */
    private $salt;

    /* Iether a BRP or CC person URI */
    private $roles;

    /* Always true */
    private $isActive;

    /* Iether a BRP or CC person URI */
    private $person;

    /* Iether a kvk or wrc organization OR cc organization URI */
    private $organization;

    /* Either user, organisation, person, application */
    private $type;

    /* Either true or false if a user is a resident */
    private $resident;

    /* jwt token */
    private $authorization;

    public function __construct(string $username = '', string $password = '', string $name = '', string $salt = null, array $roles = [], $person = null, $organization = null, $type = null, bool $resident = false, string $authorization = null, $locale = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->name = $name;
        $this->salt = $salt;
        $this->roles = $roles;
        $this->person = $person;
        $this->organization = $organization;
        $this->isActive = true;
        $this->type = $type;
        $this->resident = $resident;
        $this->authorization = $authorization;
        $this->locale = $locale; // The language of this user
    }

    public function __toString()
    {
        return $this->name;
    }

    public function getRoles()
    {
        return $this->roles;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPerson()
    {
        return $this->person;
    }

    public function getOrganization()
    {
        return $this->organization;
    }

    public function setOrganization(string $organization) : self
    {
        $this->organization = $organization;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getResident()
    {
        return $this->resident;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAuthorization()
    {
        return $this->authorization;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function isEnabled()
    {
        return $this->isActive;
    }

    public function eraseCredentials()
    {
    }

    // serialize and unserialize must be updated - see below
    public function serialize()
    {
        return serialize([
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt,
        ]);
    }

    public function unserialize($serialized)
    {
        list(
            $this->username,
            $this->password) = unserialize($serialized);
    }

    public function isEqualTo(UserInterface $user)
    {
        return true;
    }
}

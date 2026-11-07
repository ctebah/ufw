<?php

namespace Ufw\Helper;

/**
 * Minimal “logged in” helper (PHP auth globals + cookie). Apps typically supply a richer `Helper\User` override
 * or replace this with their own identity layer.
 */
class User extends Base
{

    /**
     * @return $this
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * @return bool
     */
    public function isLoggedOn()
    {
        return $_SERVER['PHP_AUTH_USER'] && $_SERVER['PHP_AUTH_PW'] && $_COOKIE['logged'];
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        $authUser = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;

        return $this->isLoggedOn() && is_string($authUser) && strcmp($authUser, 'admin') === 0;
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return false;
    }

    /**
     * @param list<string> $roles
     * @return bool
     */
    public function hasAnyOfRoles($roles)
    {
        return false;
    }
}

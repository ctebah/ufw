<?php

namespace Ufw\Helper;

/**
 * Minimal “logged in” helper (PHP auth globals + cookie). Apps typically supply a richer `Helper\User` override
 * or replace this with their own identity layer.
 */
class User extends Base
{

    public function __invoke()
    {
        return $this;
    }

    public function isLoggedOn()
    {
        return $_SERVER['PHP_AUTH_USER'] && $_SERVER['PHP_AUTH_PW'] && $_COOKIE['logged'];
    }

    public function isAdmin()
    {
        return $this->isLoggedOn() && !strcmp($_SERVER['PHP_AUTH_USER'], 'admin');
    }
}

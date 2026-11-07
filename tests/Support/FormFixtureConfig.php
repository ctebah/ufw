<?php
namespace Ufw\Tests\Support;

trait FormFixtureConfig
{
    private static function formFixtureAppConfig()
    {
        return array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        );
    }
}

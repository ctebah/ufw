<?php
namespace Ufw\Tests\Support;

class ApiClientMagicErrorProbe extends ApiClientTestApi
{
    /**
     * @return null
     */
    public function getClient()
    {
        return null;
    }
}

class ApiClientMagicBadRequesterProbe extends ApiClientTestApi
{
    /**
     * @return object
     */
    public function getClient()
    {
        return new class {
            public function getRequester()
            {
                return new class {
                };
            }
        };
    }
}

class ApiClientMagicWrongResponseProbe extends ApiClientTestApi
{
    public function getClient()
    {
        return new class {
            public function getRequester()
            {
                return new class {
                    public function get()
                    {
                        return new \stdClass();
                    }
                };
            }
        };
    }
}

class ApiClientMagicBrokenChildProbe extends ApiClientTestApi
{
    public function getBroken()
    {
        return new \stdClass();
    }
}

class ApiClientMagicMissingChildProbe extends ApiClientTestApi
{
    /**
     * @return null
     */
    public function getGhost()
    {
        return null;
    }
}

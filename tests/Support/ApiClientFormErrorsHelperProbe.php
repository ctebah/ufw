<?php
namespace Ufw\Tests\Support;

use Ufw\Component\ApiClient\Helper;

/**
 * Stub {@see Helper::getErrorPaths()} for form error mapping tests.
 */
class ApiClientFormErrorsHelperProbe extends Helper
{
    /** @var list<list<string>> */
    private $paths;

    /**
     * @param list<list<string>> $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    public function getErrorPaths($map, $errors, $path = array(), $level = 0)
    {
        return $this->paths;
    }
}

<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * Read-oriented API: {@see TraitR} ({@see read()}, in-memory {@see $data}).
 */
abstract class R extends \Ufw\Component\ApiClient\Base
{
    use TraitR;
}
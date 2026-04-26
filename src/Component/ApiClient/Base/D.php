<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * Delete-only API surface: {@see TraitD} adds {@see remove()}.
 */
abstract class D extends \Ufw\Component\ApiClient\Base
{
    
    use TraitD;
}
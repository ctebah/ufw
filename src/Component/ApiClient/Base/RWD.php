<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * Full CRUD-style API: {@see TraitR}, {@see TraitW}, {@see TraitD}.
 */
abstract class RWD extends \Ufw\Component\ApiClient\Base
{
    
    use TraitR, TraitW, TraitD;
}
<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * Read + write API: {@see TraitR} and {@see TraitW}.
 */
abstract class RW extends \Ufw\Component\ApiClient\Base
{
    
    use TraitR, TraitW;
}
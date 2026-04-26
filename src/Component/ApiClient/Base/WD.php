<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * Write + delete API: {@see TraitW} and {@see TraitD}.
 */
abstract class WD extends \Ufw\Component\ApiClient\Base
{
    
    use TraitD, TraitW;
}
<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * Like {@see RW} but uses {@see TraitRE} (read path respects error state / {@see $hasErrors}).
 */
abstract class REW extends RW
{
    
    use TraitRE;
}
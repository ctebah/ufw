<?php

namespace Ufw\Form\Element;

/**
 * Optional base for composite elements using {@see SubElementTrait}.
 */
abstract class SubElementsAbstract extends Base implements SubElementsInterface
{
    use SubElementTrait;
}

<?php
namespace Ufw\Form\Validator;

/** Minimal validator without a user-defined constructor (array-definition tests). */
class PlainForTests extends Base
{
    public function isValid($value, $context = null)
    {
        return true;
    }
}

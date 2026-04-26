<?php

namespace Ufw\Db;

/**
 * Opaque SQL expression value: stringifies to the raw SQL so callers can embed literals without extra quoting.
 */
class Expression implements \JsonSerializable
{
    /**
     * Storage for the SQL expression.
     *
     * @var string
     */
    protected $_expression;

    /**
     * Instantiate an expression, which is just a string stored as
     * an instance member variable.
     *
     * @param string $expression The string containing a SQL expression.
     */
    public function __construct($expression)
    {
        $this->_expression = (string)$expression;
    }

    /**
     * @return string The string of the SQL expression stored in this object.
     */
    public function __toString()
    {
        return $this->_expression;
    }


    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->__toString();
    }
}

<?php
namespace Ufw\Db;

/**
 * SQL condition fragment extending {@see Expression}: field / operator / value (with sensible defaults for
 * `NULL`, scalars, and `IN`), or a raw expression. {@see __toString()} builds SQL using {@see Db::quote()} for values.
 */
class Condition extends Expression
{

    /**
     *
     * @var string
     */
    protected $field;

    /**
     *
     * @var int|string|int[]|string[]
     */
    protected $value;

    /**
     *
     * @var string
     */
    protected $op = null;

    public function __construct($data = array())
    {
        if ($data instanceof \Ufw\Db\Expression) {
            $this->_expression = (string)$data;
        } else {
            if (array_key_exists('field', $data)) {
                $this->setField($data['field']);
            }
            if (array_key_exists('value', $data)) {
                $this->setValue($data['value']);
            }
            if (array_key_exists('op', $data)) {
                $this->setOp($data['op']);
            }
        }
    }

    public function __toString() {
        return $this->_expression ? 
            $this->_expression : 
            $this->getField() . ' ' . $this->getOp() . ' ' . $this->getOpenBracket() . $this->getValueQuoted(\Ufw\Db\Db::class) . $this->getCloseBracket();
    }
    
    protected function getCloseBracket() {
        return in_array(strtoupper(trim($this->getOp())), ['BETWEEN', 'IS', 'IS NOT', 'RLIKE', 'REGEXP']) ? '' : ')';
    }

    protected function getOpenBracket() {
        return in_array(strtoupper(trim($this->getOp())), ['BETWEEN', 'IS', 'IS NOT', 'RLIKE', 'REGEXP']) ? '' : '(';
    }

    /**
     *
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     *
     * @return int|string|int[]|string[]
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getValueQuoted($quoter)
    {
        return is_array($this->getValue()) ? join(', ', $quoter::quote($this->getValue())) : $quoter::quote($this->getValue());
    }

    /**
     *
     * @return string
     */
    public function getOp()
    {
        if (isset($this->op)) {
            return $this->op;
        }
        return is_null($this->getValue()) ? 'IS' : (is_scalar($this->getValue()) ? '=' : 'IN');
    }

    /**
     *
     * @param string $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     *
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     *
     * @param string $op
     */
    public function setOp($op)
    {
        $this->op = $op;
    }
}
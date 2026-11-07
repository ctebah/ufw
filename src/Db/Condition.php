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
     * @var mixed
     */
    protected $value;

    /**
     * Null until setOp(); getOp() then picks IS, =, or IN from the value.
     *
     * @var string|null
     */
    protected $op = null;

    /**
     * @param array<string, mixed>|\Ufw\Db\Expression $data
     */
    public function __construct($data = array())
    {
        if ($data instanceof \Ufw\Db\Expression) {
            $this->_expression = (string)$data;
        } else {
            if (array_key_exists('field', $data) && is_string($data['field'])) {
                $this->setField($data['field']);
            }
            if (array_key_exists('value', $data)) {
                $this->setValue($data['value']);
            }
            if (array_key_exists('op', $data) && is_string($data['op'])) {
                $this->setOp($data['op']);
            }
        }
    }

    public function __toString() {
        return $this->_expression ? 
            $this->_expression : 
            $this->getField() . ' ' . $this->getOp() . ' ' . $this->getOpenBracket() . $this->getValueQuoted(\Ufw\Db\Db::class) . $this->getCloseBracket();
    }
    
    /**
     * @return string
     */
    protected function getCloseBracket() {
        return in_array(strtoupper(trim($this->getOp())), ['BETWEEN', 'IS', 'IS NOT', 'RLIKE', 'REGEXP']) ? '' : ')';
    }

    /**
     * @return string
     */
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
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param class-string<\Ufw\Db\Db> $quoter
     * @return string
     */
    public function getValueQuoted($quoter)
    {
        $quoted = $quoter::quote($this->getValue());
        if (is_array($quoted)) {
            $parts = array();
            foreach ($quoted as $part) {
                if (is_int($part) || is_string($part)) {
                    $parts[] = (string) $part;
                }
            }
            return join(', ', $parts);
        }
        return (string) $quoted;
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
     * @return void
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     *
     * @param mixed $value
     * @return void
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     *
     * @param string $op
     * @return void
     */
    public function setOp($op)
    {
        $this->op = $op;
    }
}
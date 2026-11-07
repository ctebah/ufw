<?php
namespace Ufw;

/**
 * Collects string fragments for a named layout slot (`append`, `prepend`, `set`, optional output buffering)
 * and concatenates them with a configurable glue in {@see __toString()}.
 */
class Placeholder
{

    /**
     * @var list<string>
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $glue = '';

    /**
     * @param string $value
     * @return $this
     */
    public function append($value)
    {
        $this->data[] = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function captureEnd()
    {
        ob_end_clean();
        return $this;
    }

    /**
     * @param string $mode set|append|prepend
     * @return void
     */
    public function captureStart($mode = 'append')
    {
        ob_start(array(
            $this,
            $mode
        ));
    }

    /**
     * @return list<string>
     */
    public function get()
    {
        return $this->data;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function prepend($value)
    {
        array_unshift($this->data, $value);
        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function set($value)
    {
        $this->data = [$value];
        return $this;
    }

    public function __toString()
    {
        return join($this->getGlue(), $this->data);
    }

    /**
     * @return string
     */
    public function getGlue()
    {
        return $this->glue;
    }

    /**
     * @param string $glue
     * @return $this
     */
    public function setGlue($glue)
    {
        $this->glue = $glue;
        return $this;
    }
}

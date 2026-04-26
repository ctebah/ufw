<?php
namespace Ufw;

/**
 * Collects string fragments for a named layout slot (`append`, `prepend`, `set`, optional output buffering)
 * and concatenates them with a configurable glue in {@see __toString()}.
 */
class Placeholder
{

    protected $data = [];

    /**
     * glue parts separator
     *
     * @var string
     */
    protected $glue = '';

    public function append($value)
    {
        $this->data[] = $value;
        return $this;
    }

    public function captureEnd()
    {
        ob_end_clean();
        return $this;
    }

    /**
     *
     * @param string $mode
     *            set|append|prepend
     */
    public function captureStart($mode = 'append')
    {
        ob_start(array(
            $this,
            $mode
        ));
    }

    public function get()
    {
        return $this->data;
    }

    public function prepend($value)
    {
        array_unshift($this->data, $value);
        return $this;
    }

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
     *
     * @return string
     */
    public function getGlue()
    {
        return $this->glue;
    }

    /**
     *
     * @param string $glue
     */
    public function setGlue($glue)
    {
        $this->glue = $glue;
        return $this;
    }
}

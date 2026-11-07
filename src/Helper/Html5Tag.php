<?php

namespace Ufw\Helper;

/**
 * Builds HTML5 element strings (open/close/void/self-closing) with attribute serialization via the
 * {@see Html5Attribs} helper; maintains a small stack so {@see close()} matches the last {@see open()}.
 */
class Html5Tag extends Base
{

    /**
     * @var list<string>
     */
    protected $heap = array();

    /**
     * @return $this
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * @param string $tag
     * @param array<string, mixed> $attribs
     * @return string
     */
    public function open($tag, $attribs)
    {
        array_push($this->heap, $tag);
        return '<' . $tag . ($attribs ? ' ' . $this->html5Attribs($attribs) : '') . '>';
    }

    /**
     * @param string $tag
     * @param array<string, mixed> $attribs
     * @return string
     */
    public function selfClosing($tag, $attribs)
    {
        return '<' . $tag . ($attribs ? ' ' . $this->html5Attribs($attribs) : '') . ' />';
    }

    /**
     * @param string $tag
     * @param array<string, mixed> $attribs
     * @return string
     */
    public function void($tag, $attribs)
    {
        return '<' . $tag . ($attribs ? ' ' . $this->html5Attribs($attribs) : '') . '>';
    }

    /**
     * @return string
     */
    public function close()
    {
        $tag = array_pop($this->heap);
        return '</' . $tag . '>';
    }

    /**
     * @param string $tag
     * @param array<string, mixed> $attribs
     * @param string $content
     * @return string
     */
    public function full($tag, $attribs, $content)
    {
        return $this->open($tag, $attribs) . $content . $this->close();
    }
}

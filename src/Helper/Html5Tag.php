<?php

namespace Ufw\Helper;

/**
 * Builds HTML5 element strings (open/close/void/self-closing) with attribute serialization via the
 * {@see Html5Attribs} helper; maintains a small stack so {@see close()} matches the last {@see open()}.
 */
class Html5Tag extends Base
{

    protected $heap = array();

    public function __invoke()
    {
        return $this;
    }

    public function open($tag, $attribs)
    {
        array_push($this->heap, $tag);
        return '<' . $tag . ($attribs ? ' ' . $this->html5Attribs($attribs) : '') . '>';
    }

    public function selfClosing($tag, $attribs)
    {
        return '<' . $tag . ($attribs ? ' ' . $this->html5Attribs($attribs) : '') . ' />';
    }

    public function void($tag, $attribs)
    {
        return '<' . $tag . ($attribs ? ' ' . $this->html5Attribs($attribs) : '') . '>';
    }

    public function close()
    {
        $tag = array_pop($this->heap);
        return '</' . $tag . '>';
    }

    public function full($tag, $attribs, $content)
    {
        return $this->open($tag, $attribs) . $content . $this->close();
    }
}

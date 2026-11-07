<?php
namespace Ufw\Helper;

/**
 * JSON-encodes a translated string for safe embedding in JavaScript (inline scripts, `json_encode` output, etc.).
 * Uses the same {@see Translate} pipeline as `$this->translate()` (resolved through the controller helper).
 * Invokable so the helper can be used as `$helper('…')` after resolution by name.
 */
class JsTranslate extends Base
{

    /**
     * @param string $str
     * @return string
     */
    public function __invoke($str)
    {
        return $this->jsTranslate($str);
    }
    
    /**
     * @param string $str
     * @return string
     */
    public function jsTranslate($str)
    {
        $encoded = json_encode($this->translate($str));
        return $encoded === false ? '' : $encoded;
    }
    
}

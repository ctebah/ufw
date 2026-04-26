<?php
namespace Ufw\Utils;

/**
 * URI/controller string shaping (singleton): {@see formatToPhpFriendlyName()} and related helpers for dispatch naming.
 *
 * @method static \Ufw\Utils\Strings getInstance()
 */
class Strings
{
    use \Ufw\Singleton;

    /**
     * Word delimiter characters
     *
     * @var array
     */
    protected $wordDelimiter = array(
        '-',
        '.',
    );

    /**
     * Formats a string from a URI into a PHP-friendly name.
     *
     * By default, replaces words separated by the word separator character(s)
     * with camelCaps. If $isAction is false, it also preserves replaces words
     * separated by the path separation character with an underscore, making
     * the following word Title cased. All non-alphanumeric characters are
     * removed.
     *
     * @param string $unformatted
     * @param boolean $isAction
     *            Defaults to false
     * @return string
     */
    public function formatToPhpFriendlyName($unformatted, $suffix = '', $wordDelimiters = null)
    {
        $segments = (array) $unformatted;
        
        foreach ($segments as $key => $segment) {
            $segment = str_replace($wordDelimiters ?? $this->getWordDelimiter(), ' ', strtolower($segment));
            $segment = preg_replace('/[^a-z0-9 ]/', '', $segment);
            $segments[$key] = str_replace(' ', '', ucwords($segment));
        }
        
        $formatted = join('_', $segments);
        return strtolower(substr($formatted, 0, 1)) . substr($formatted, 1) . $suffix;
    }

    /**
     *
     * @return array
     */
    public function getWordDelimiter()
    {
        return $this->wordDelimiter;
    }

    /**
     *
     * @param array $wordDelimiter
     */
    public function setWordDelimiter(array $wordDelimiter)
    {
        $this->wordDelimiter = $wordDelimiter;
        return $this;
    }
}

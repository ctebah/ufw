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
     * @var array<int, string>
     */
    protected $wordDelimiter = array(
        '-',
        '.',
    );

    /**
     * Formats a string from a URI into a PHP-friendly name.
     *
     * By default, replaces words separated by the word separator character(s)
     * with camelCaps. Segments joined with `_` become lowerCamelCase as a whole.
     * All non-alphanumeric characters are removed.
     *
     * @param string|array<int, string> $unformatted
     * @param string $suffix
     * @param array<int, string>|null $wordDelimiters
     * @return string
     */
    public function formatToPhpFriendlyName($unformatted, $suffix = '', $wordDelimiters = null)
    {
        $segments = (array) $unformatted;
        
        foreach ($segments as $key => $segment) {
            $segment = str_replace($wordDelimiters ?? $this->getWordDelimiter(), ' ', strtolower($segment));
            $segment = $this->stripNonAlphanumeric($segment);
            $segments[$key] = str_replace(' ', '', ucwords($segment));
        }
        
        $formatted = join('_', $segments);
        return strtolower(substr($formatted, 0, 1)) . substr($formatted, 1) . $suffix;
    }

    /**
     * @param string $segment
     * @return string
     */
    protected function stripNonAlphanumeric($segment)
    {
        $result = $this->pregReplaceStrip($segment);
        if (!is_string($result)) {
            return '';
        }

        return $result;
    }

    /**
     * @param string $segment
     * @return string|null
     */
    protected function pregReplaceStrip($segment)
    {
        return preg_replace('/[^a-z0-9 ]/', '', $segment);
    }

    /**
     * @return array<int, string>
     */
    public function getWordDelimiter()
    {
        return $this->wordDelimiter;
    }

    /**
     * @param array<int, string> $wordDelimiter
     * @return $this
     */
    public function setWordDelimiter(array $wordDelimiter)
    {
        $this->wordDelimiter = $wordDelimiter;
        return $this;
    }
}

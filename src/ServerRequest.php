<?php
namespace Ufw;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Diactoros {@see \Laminas\Diactoros\ServerRequest} with an additional mutable {@see $attributes} bag merged into PSR-7 attribute access.
 */
class ServerRequest extends \Laminas\Diactoros\ServerRequest
{

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [];

    /**
     * @param array<string, mixed>|array<int|string, mixed> $serverParams Server parameters, typically from $_SERVER
     * @param array<string, mixed>|array<int|string, mixed> $uploadedFiles Upload file information, a tree of UploadedFiles
     * @param null|string|UriInterface $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array<string, mixed> $headers Headers for the message, if any.
     * @param array<int|string, mixed> $cookies Cookies for the message, if any.
     * @param array<int|string, mixed> $queryParams Query params for the message, if any.
     * @param null|array<int|string, mixed>|object $parsedBody The deserialized body parameters, if any.
     * @param string $protocol HTTP protocol version.
     * @param array<string, mixed> $attributes
     * @throws InvalidArgumentException for any invalid value.
     */
    public function __construct(
        array $serverParams = [], 
        array $uploadedFiles = [], 
        $uri = null, 
        $method = null, 
        $body = 'php://input', 
        array $headers = [], 
        array $cookies = [], 
        array $queryParams = [], 
        $parsedBody = null, 
        $protocol = '1.1', 
        array $attributes = []
    ) {
        parent::__construct($serverParams, $uploadedFiles, $uri, $method, $body, $headers, $cookies, $queryParams, $parsedBody, $protocol);
        $this->attributes = $attributes;
    }

    /**
     * @param array<int|string, mixed> $uploadedFiles
     * @return void
     * @throws InvalidArgumentException if any leaf is not an UploadedFileInterface instance.
     */
    protected function validateUploadedFiles(array $uploadedFiles)
    {
        foreach ($uploadedFiles as $file) {
            if (is_array($file)) {
                $this->validateUploadedFiles($file);
                continue;
            }
            
            if (!$file instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('Invalid leaf in uploaded files structure');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function setAttributes($attributes) {
        $this->attributes = $attributes;
        return $this;
    }

    public function getAttribute($attribute, $default = null)
    {
        if (!array_key_exists($attribute, $this->attributes)) {
            return $default;
        }
        
        return $this->attributes[$attribute];
    }

    public function withAttribute($attribute, $value): ServerRequest
    {
        $new = clone $this;
        $new->attributes[$attribute] = $value;
        return $new;
    }

    public function withoutAttribute($attribute): ServerRequest
    {
        $new = clone $this;
        unset($new->attributes[$attribute]);
        return $new;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getCookieParams(): array
    {
        return parent::getCookieParams();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getQueryParams(): array
    {
        return parent::getQueryParams();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getServerParams(): array
    {
        return parent::getServerParams();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getUploadedFiles(): array
    {
        return parent::getUploadedFiles();
    }

    /**
     * @return array<int|string, mixed>|object|null
     */
    #[\ReturnTypeWillChange]
    public function getParsedBody()
    {
        return parent::getParsedBody();
    }
}

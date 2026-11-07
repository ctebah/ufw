<?php

namespace Ufw\Form\Validator;

/**
 * Validates the field value against the configured CSRF component’s static {@code validate()} (default {@see \Ufw\Component\Csrf}).
 */
class Csrf extends Base
{
    const INVALID = 'csrf-invalid';

    /**
     * @var string
     */
    protected $purpose;

    /**
     * @var class-string
     */
    protected $componentClass;

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = [
        self::INVALID => 'Security token invalid or expired. Please reload the form and try again.',
    ];

    /**
     * @param class-string $componentClass Class with static {@code validate(string $purpose, $submitted): bool} (typically {@see \Ufw\Component\Csrf} or a subclass).
     */
    public function __construct(string $purpose = 'default', string $componentClass = \Ufw\Component\Csrf::class)
    {
        $this->purpose = $purpose;
        $this->componentClass = $componentClass;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        $this->setValue($value);
        $cls = $this->componentClass;
        if (!$cls::validate($this->purpose, $value)) {
            $this->error(self::INVALID);

            return false;
        }

        return true;
    }
}

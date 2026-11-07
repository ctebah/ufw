<?php
namespace Ufw\Form\Element;

/**
 * Composite element: nested fields sharing parent form context, sub-values, and validation.
 */
interface SubElementsInterface
{

    /**
     * @param \Ufw\Form\Base|null $form
     * @return $this
     */
    public function setForm(\Ufw\Form\Base $form = null);

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return $this
     */
    public function setValue($value, $context = null);

    /**
     * @return \Generator<string, mixed>
     */
    public function yieldSubValues();

    /**
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($context = null);
}

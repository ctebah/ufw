<?php
/**
 * @var \Ufw\Form\Element\Checkbox $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $hasSuccess = @strlen($element->getValue()) && !$hasErrors && $element->getForm()->getValidated();
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <div class="checkbox">
        <?php echo $this->html5Tag()->void('input', ['name' => $element->getName(), 'id' => $element->getId() . '-pseudo', 'value' => $element->getUncheckedValue(), 'type' => 'hidden']); ?>
        <?php echo $this->html5Tag()->void('input', $element->getAttribs() + ['value' => $element->getCheckedValue(), 'checked' => $element->getChecked()]); ?>
        <label for="<?php echo $element->getId(); ?>">
            <?php echo $element->getLabel(); ?>
        </label>
    </div>
    <?php if ($element->getDescription()): ?>
        <span class="form-text text-muted"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>

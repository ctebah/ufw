<?php 
/**
 * @var \Ufw\Form\Element\Checkbox $element
 * @var \Ufw\Helper\Render $this
 *
 * Checkmark glyphs use {@code <i class="material-icons">} with PUA codepoints. Include Material Icons
 * (e.g. Google Web Font) in the page layout/assets; without it, icons may render as missing-glyph boxes.
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $elementValue = $element->getValue();
    $hasSuccess = is_string($elementValue) && $elementValue !== '' && !$hasErrors && ($form = $element->getForm()) !== null && $form->getValidated();
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <div class="checkbox">
        <?php echo $this->html5Tag()->void('input', ['name' => $element->getName(), 'id' => $element->getId() . '-pseudo', 'value' => $element->getUncheckedValue(), 'type' => 'hidden']); ?>
        <?php echo $this->html5Tag()->void('input', $element->getAttribs() + ['value' => $element->getCheckedValue(), 'checked' => $element->getChecked()]); ?>
        <label for="<?php echo $element->getId(); ?>">
            <i class="material-icons checked">&#xE834;</i>
			<i class="material-icons unchecked">&#xE835;</i>
            <?php echo $element->getLabel(); ?>
        </label>
    </div>
    <?php if ($element->getDescription()): ?>
        <span class="help-block"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>
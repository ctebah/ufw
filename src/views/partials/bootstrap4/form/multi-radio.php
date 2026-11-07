<?php 
/**
 * @var \Ufw\Form\Element\Radio $element
 * @var \Ufw\Helper\Render $this
 *
 * Option markers use {@code <i class="material-icons">} with PUA codepoints. Include Material Icons
 * (e.g. Google Web Font) in the page layout/assets; without it, icons may render as missing-glyph boxes.
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $elementValue = $element->getValue();
    $hasSuccess = is_string($elementValue) && $elementValue !== '' && !$hasErrors && ($form = $element->getForm()) !== null && $form->getValidated();
    $ord = 1;
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <?php if ($element->getLabel()): ?>
        <label class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif;?>
    
    <?php foreach ($element->getMultiOptions() as $optKey => $optValue): ?>
        <div class="radio">
            <?php echo $this->html5Tag()->void('input', ['value' => $optKey, 'id' => $element->getId() . '-' . $ord, 'checked' => is_string($elementValue = $element->getValue()) && $elementValue == $optKey] + $element->getAttribs()); ?>
            <label for="<?php echo $element->getId() . '-' . $ord++; ?>">
                <?php echo htmlspecialchars(is_string($optValue) ? $optValue : '', ENT_QUOTES | ENT_HTML5); ?>
                <i class="material-icons checked">&#xE837;</i>
                <i class="material-icons unchecked">&#xE836;</i>
            </label>
        </div>
    <?php endforeach; ?>
    
    <?php if ($hasErrors): ?>
    	<span id="<?php echo $element->getId(); ?>-errors" class="form-text text-muted"><?php echo join('<br>', $errors); ?></span>
    <?php endif; ?>
    
    <?php if ($element->getDescription()): ?>
        <span class="form-text text-muted"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>

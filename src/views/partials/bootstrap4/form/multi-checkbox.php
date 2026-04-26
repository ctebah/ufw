<?php
/**
 * @var \Ufw\Form\Element\CheckboxSet $element
 * @var \Ufw\Helper\Render $this
 *
 * Option markers use {@code <i class="material-icons">} with PUA codepoints. Include Material Icons
 * (e.g. Google Web Font) in the page layout/assets; without it, icons may render as missing-glyph boxes.
 */

$errors = $element->getErrors();
$hasErrors = !!$errors;
$hasSuccess = @array_filter($element->getValue(), 'strlen') && !$hasErrors && $element->getForm()->getValidated();
$ord = 1;
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <?php if ($element->getLabel()): ?>
        <label class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif;?>

    <?php foreach ($element->getMultiOptions() as $optKey => $optValue): ?>
        <div class="checkbox">
            <?php echo $this->html5Tag()->void('input', ['value' => $optKey, 'id' => $element->getId() . '-' . $ord, 'checked' => @in_array($optKey, $element->getValue())] + $element->getAttribs()); ?>
            <label for="<?php echo $element->getId() . '-' . $ord++; ?>">
                <?php echo htmlspecialchars($optValue, ENT_QUOTES | ENT_HTML5); ?>
                <i class="material-icons checked">&#xE834;</i>
                <i class="material-icons unchecked">&#xE835;</i>
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

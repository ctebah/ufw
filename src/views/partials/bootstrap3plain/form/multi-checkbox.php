<?php
/**
 * @var \Ufw\Form\Element\CheckboxSet $element
 * @var \Ufw\Helper\Render $this
 */

$errors = $element->getErrors();
$hasErrors = !!$errors;
$hasSuccess = (bool) array_filter((array) $element->getValue(), function ($v) { return is_string($v) && $v !== ''; }) && !$hasErrors && ($form = $element->getForm()) !== null && $form->getValidated();
$ord = 1;
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <?php if ($element->getLabel()): ?>
        <label class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif;?>

    <?php foreach ($element->getMultiOptions() as $optKey => $optValue): ?>
        <div class="checkbox">
            <?php echo $this->html5Tag()->void('input', ['value' => $optKey, 'id' => $element->getId() . '-' . $ord, 'checked' => is_array($selectedValues = $element->getValue()) && in_array($optKey, $selectedValues, false)] + $element->getAttribs()); ?>
            <label for="<?php echo $element->getId() . '-' . $ord++; ?>">
                <?php echo htmlspecialchars(is_string($optValue) ? $optValue : '', ENT_QUOTES | ENT_HTML5); ?>
            </label>
        </div>
    <?php endforeach; ?>

    <?php if ($hasErrors): ?>
        <span id="<?php echo $element->getId(); ?>-errors" class="help-block"><?php echo join('<br>', $errors); ?></span>
    <?php endif; ?>

    <?php if ($element->getDescription()): ?>
        <span class="help-block"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>

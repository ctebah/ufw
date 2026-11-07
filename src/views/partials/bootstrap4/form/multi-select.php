<?php
/**
 * @var \Ufw\Form\Element\Select $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $elementValue = $element->getValue();
    $hasSuccess = ($element->getAllowMultiple()
        ? (bool) array_filter((array) $elementValue, function ($v) {
            return is_string($v) && $v !== '';
        })
        : (is_string($elementValue) && $elementValue !== ''))
        && !$hasErrors && ($form = $element->getForm()) !== null && $form->getValidated();
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'above')): ?>
        <label class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif;?>
    
    <?php echo $this->html5Tag()->open('select', $element->getAttribs()); ?>
    
    <?php foreach ($element->getMultiOptions() as $optKey => $optValue): ?>
        <?php
        $elementValue = $element->getValue();
        $selected = $element->getAllowMultiple()
            ? (is_array($elementValue) && in_array($optKey, $elementValue, false))
            : (is_string($elementValue) && $elementValue !== '' && $elementValue == $optKey);
        $optLabel = is_string($optValue) ? $optValue : '';
        ?>
        <?php echo $this->html5Tag()->full('option', ['value' => $optKey, 'selected' => $selected], htmlspecialchars(is_string($optValue) ? $optValue : '', ENT_QUOTES | ENT_HTML5)); ?>
    <?php endforeach; ?>
    
    <?php echo $this->html5Tag()->close(); ?>

    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'below')): ?>
        <label for="<?php echo $element->getId(); ?>" class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>
    
    <?php if ($element->getIcon()): ?>
        <?php echo $element->getIcon(); ?>
    <?php else: ?>
        <?php if ($hasErrors): ?>
            <span id="<?php echo $element->getId(); ?>-errors" class="form-text text-muted"><?php echo join('<br>', $errors); ?></span>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($element->getDescription()): ?>
        <span class="form-text text-muted"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>

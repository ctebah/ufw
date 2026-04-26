<?php
/**
 * @var \Ufw\Form\Element\Select $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $hasSuccess = ($element->getAllowMultiple() ? @array_filter($element->getValue(), 'strlen') : @strlen($element->getValue()))
        && !$hasErrors && $element->getForm()->getValidated();
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'above')): ?>
        <label class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif;?>
    
    <?php echo $this->html5Tag()->open('select', $element->getAttribs()); ?>
    
    <?php foreach ($element->getMultiOptions() as $optKey => $optValue): ?>
        <?php $selected = $element->getAllowMultiple() ? in_array($optKey, $element->getValue()) : @strlen($element->getValue()) && ($element->getValue() == $optKey); ?>
        <?php echo $this->html5Tag()->full('option', ['value' => $optKey, 'selected' => $selected], htmlspecialchars($optValue, ENT_QUOTES | ENT_HTML5)); ?>
    <?php endforeach; ?>
    
    <?php echo $this->html5Tag()->close('select'); ?>

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

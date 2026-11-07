<?php 
/**
 * @var \Ufw\Form\Element\Textarea $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $elementValue = $element->getValue();
    $hasSuccess = is_string($elementValue) && $elementValue !== '' && !$hasErrors && ($form = $element->getForm()) !== null && $form->getValidated(); 
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'above')): ?>
        <label for="<?php echo $element->getId(); ?>" class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>
    <?php echo $this->html5Tag()->full('textarea', $element->getAttribs(), is_string($elementValue) ? $elementValue : ''); ?>
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'below')): ?>
        <label for="<?php echo $element->getId(); ?>" class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>
    
    <?php if ($element->getDescription()): ?>
        <span class="help-block"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>

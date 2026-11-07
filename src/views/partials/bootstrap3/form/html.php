<?php 
/**
 * @var \Ufw\Form\Element\Html $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $elementValue = $element->getValue();
    $hasSuccess = is_string($elementValue) && $elementValue !== '' && !$hasErrors && ($form = $element->getForm()) !== null && $form->getValidated(); 
?>
<div class="form-group form-html-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'above')): ?>
        <label for="<?php echo $element->getId(); ?>" class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>
    <?php $htmlContent = $element->getValue();
echo $this->html5Tag()->full('div', $element->getAttribs(), is_string($htmlContent) ? $htmlContent : ''); ?>
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'below')): ?>
        <label for="<?php echo $element->getId(); ?>" class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>
    
    <?php if ($element->getDescription()): ?>
        <span class="help-block"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>

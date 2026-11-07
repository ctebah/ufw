<?php 
/**
 * @var \Ufw\Form\Element\Textarea $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $elementValue = $element->getValue();
    $hasSuccess = is_string($elementValue) && $elementValue !== '' && !$hasErrors && ($form = $element->getForm()) !== null && $form->getValidated();

    $attribs = $element->getAttribs();
    $classAttrib = $attribs['class'] ?? '';
    $baseClass = is_string($classAttrib) ? $classAttrib : '';
    if ($hasErrors) {
        $attribs['class'] = trim($baseClass . ' is-invalid');
        $attribs['aria-describedby'] = $element->getId() . '-errors';
        $attribs['aria-invalid'] = 'true';
    } elseif ($hasSuccess) {
        $attribs['class'] = trim($baseClass . ' is-valid');
    }
?>
<div class="mb-3 <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : ''; ?>">
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'above')): ?>
        <label for="<?php echo $element->getId(); ?>" class="form-label <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>
    <?php $textareaContent = $element->getValue();
    echo $this->html5Tag()->full('textarea', $attribs, is_string($textareaContent) ? $textareaContent : ''); ?>
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'below')): ?>
        <label for="<?php echo $element->getId(); ?>" class="form-label <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>

    <?php if ($hasErrors): ?>
        <div id="<?php echo $element->getId(); ?>-errors" class="invalid-feedback d-block"><?php echo join('<br>', $errors); ?></div>
    <?php endif; ?>

    <?php if ($element->getDescription()): ?>
        <div class="form-text"><?php echo $element->getDescription(); ?></div>
    <?php endif; ?>
</div>

<?php
/**
 * @var \Ufw\Form\Element\Select $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $hasSuccess = ($element->getAllowMultiple() ? @array_filter($element->getValue(), 'strlen') : @strlen($element->getValue()))
        && !$hasErrors && $element->getForm()->getValidated();

    $attribs = $element->getAttribs();
    $baseClass = $attribs['class'] ?? '';
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
        <label class="form-label <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif;?>

    <?php echo $this->html5Tag()->open('select', $attribs); ?>

    <?php foreach ($element->getMultiOptions() as $optKey => $optValue): ?>
        <?php $selected = $element->getAllowMultiple() ? in_array($optKey, $element->getValue()) : @strlen($element->getValue()) && ($element->getValue() == $optKey); ?>
        <?php echo $this->html5Tag()->full('option', ['value' => $optKey, 'selected' => $selected], htmlspecialchars($optValue, ENT_QUOTES | ENT_HTML5)); ?>
    <?php endforeach; ?>

    <?php echo $this->html5Tag()->close('select'); ?>

    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'below')): ?>
        <label for="<?php echo $element->getId(); ?>" class="form-label <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>

    <?php if ($element->getIcon()): ?>
        <?php echo $element->getIcon(); ?>
    <?php elseif ($hasErrors): ?>
        <div id="<?php echo $element->getId(); ?>-errors" class="invalid-feedback d-block"><?php echo join('<br>', $errors); ?></div>
    <?php endif; ?>

    <?php if ($element->getDescription()): ?>
        <div class="form-text"><?php echo $element->getDescription(); ?></div>
    <?php endif; ?>
</div>

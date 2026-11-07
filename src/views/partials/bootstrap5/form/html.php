<?php 
/**
 * @var \Ufw\Form\Element\Html $element
 * @var \Ufw\Helper\Render $this
 */

?>
<div class="mb-3 form-html-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : ''; ?>">
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'above')): ?>
        <label for="<?php echo $element->getId(); ?>" class="form-label <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>
    <?php $htmlContent = $element->getValue();
echo $this->html5Tag()->full('div', $element->getAttribs(), is_string($htmlContent) ? $htmlContent : ''); ?>
    <?php if ($element->getLabel() && ($element->getLabelPlacement() == 'below')): ?>
        <label for="<?php echo $element->getId(); ?>" class="form-label <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
    <?php endif; ?>

    <?php if ($element->getDescription()): ?>
        <div class="form-text"><?php echo $element->getDescription(); ?></div>
    <?php endif; ?>
</div>

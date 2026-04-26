<?php 
/**
 * @var \Ufw\Form\Element\Button $element
 * @var \Ufw\Helper\Render $this
 */

?>
<div class="mb-3 <?php echo $element->getWrapClass(); ?>">
    <?php echo $this->html5Tag()->full('button', $element->getAttribs(), $element->getCaption()); ?>
</div>

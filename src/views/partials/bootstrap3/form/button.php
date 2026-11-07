<?php 
/**
 * @var \Ufw\Form\Element\Button $element
 * @var \Ufw\Helper\Render $this
 */

?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', ''; ?> ">
    <?php echo $this->html5Tag()->full('button', $element->getAttribs(), (string) ($element->getCaption() ?? '')); ?>
</div>

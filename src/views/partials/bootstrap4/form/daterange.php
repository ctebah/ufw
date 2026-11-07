<?php 
/**
 * @var \Ufw\Form\Element\DateRange $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasErrors ? 'has-error has-feedback ' : ''; ?>">
    <div class="input-group input-daterange" <?php echo $this->html5Attribs(\Ufw\preg_grep_keys('~^data\-~', $element->getAttribs())); ?>>
        <?php echo $this->html5Tag()->void('input', ['id' => $element->getId() . '-from', 'name' => $element->getName() . '[from]'] + $element->getAttribs() + ['value' => $element->getValueFrom()] + ($hasErrors ? ['aria-describedby' => $element->getId() . '-errors'] : [])); ?>
        <?php if ($element->getLabel()): ?>
            <label for="<?php echo $element->getId(); ?>" class="<?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
        <?php endif; ?>
        <?php $separator = $element->getOption('separator'); ?>
        <div class="input-group-addon"><?php echo is_string($separator) ? $separator : ''; ?></div>
        <?php echo $this->html5Tag()->void('input', ['id' => $element->getId() . '-to', 'name' => $element->getName() . '[to]'] + $element->getAttribs() + ['value' => $element->getValueTo()] + ($hasErrors ? ['aria-describedby' => $element->getId() . '-errors'] : [])); ?>
    </div>
    
    <?php if ($hasErrors): ?>
        <span class="glyphicon glyphicon-remove form-control-feedback" aria-hidden="true"></span>
        <span id="<?php echo $element->getId(); ?>-errors" class="form-text text-muted"><?php echo join('<br>', $errors); ?></span>
    <?php endif; ?>
    
    <?php if ($element->getDescription()): ?>
        <span class="form-text text-muted"><?php echo $element->getDescription(); ?></span>
    <?php endif; ?>
</div>

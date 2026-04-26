<?php 
/**
 * @var \Ufw\Form\Element\DateRange $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $hasSuccess = false;

    $fromAttribs = ['id' => $element->getId() . '-from', 'name' => $element->getName() . '[from]'] + $element->getAttribs() + ['value' => $element->getValueFrom()];
    $toAttribs = ['id' => $element->getId() . '-to', 'name' => $element->getName() . '[to]'] + $element->getAttribs() + ['value' => $element->getValueTo()];
    if ($hasErrors) {
        $desc = $element->getId() . '-errors';
        $fromAttribs['aria-describedby'] = $desc;
        $toAttribs['aria-describedby'] = $desc;
        $fromAttribs['aria-invalid'] = 'true';
        $toAttribs['aria-invalid'] = 'true';
        $fc = 'form-control is-invalid';
        $fromAttribs['class'] = trim(($fromAttribs['class'] ?? '') . ' ' . $fc);
        $toAttribs['class'] = trim(($toAttribs['class'] ?? '') . ' ' . $fc);
    }
?>
<div class="mb-3 <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : ''; ?>">
    <div class="input-group input-daterange" <?php echo $this->html5Attribs(preg_grep_keys('~^data\-~', $element->getAttribs())); ?>>
        <?php echo $this->html5Tag()->void('input', $fromAttribs); ?>
        <?php if ($element->getLabel()): ?>
            <label for="<?php echo $element->getId(); ?>" class="input-group-text <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
        <?php endif; ?>
        <span class="input-group-text"><?php echo $element->getOption('separator'); ?></span>
        <?php echo $this->html5Tag()->void('input', $toAttribs); ?>
    </div>

    <?php if ($hasErrors): ?>
        <div id="<?php echo $element->getId(); ?>-errors" class="invalid-feedback d-block"><?php echo join('<br>', $errors); ?></div>
    <?php endif; ?>

    <?php if ($element->getDescription()): ?>
        <div class="form-text"><?php echo $element->getDescription(); ?></div>
    <?php endif; ?>
</div>

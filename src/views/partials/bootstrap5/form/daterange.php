<?php 
/**
 * @var \Ufw\Form\Element\DateRange $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;

    $fromAttribs = ['id' => $element->getId() . '-from', 'name' => $element->getName() . '[from]'] + $element->getAttribs() + ['value' => $element->getValueFrom()];
    $toAttribs = ['id' => $element->getId() . '-to', 'name' => $element->getName() . '[to]'] + $element->getAttribs() + ['value' => $element->getValueTo()];
    if ($hasErrors) {
        $desc = $element->getId() . '-errors';
        $fromAttribs['aria-describedby'] = $desc;
        $toAttribs['aria-describedby'] = $desc;
        $fromAttribs['aria-invalid'] = 'true';
        $toAttribs['aria-invalid'] = 'true';
        $fc = 'form-control is-invalid';
        $fromClassAttrib = $fromAttribs['class'] ?? '';
        $fromClass = is_string($fromClassAttrib) ? $fromClassAttrib : '';
        $fromAttribs['class'] = trim($fromClass . ' ' . $fc);
        $toClassAttrib = $toAttribs['class'] ?? '';
        $toClass = is_string($toClassAttrib) ? $toClassAttrib : '';
        $toAttribs['class'] = trim($toClass . ' ' . $fc);
    }
?>
<div class="mb-3 <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : ''; ?>">
    <div class="input-group input-daterange" <?php echo $this->html5Attribs(\Ufw\preg_grep_keys('~^data\-~', $element->getAttribs())); ?>>
        <?php echo $this->html5Tag()->void('input', $fromAttribs); ?>
        <?php if ($element->getLabel()): ?>
            <label for="<?php echo $element->getId(); ?>" class="input-group-text <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></label>
        <?php endif; ?>
        <?php $separator = $element->getOption('separator'); ?>
        <span class="input-group-text"><?php echo is_string($separator) ? $separator : ''; ?></span>
        <?php echo $this->html5Tag()->void('input', $toAttribs); ?>
    </div>

    <?php if ($hasErrors): ?>
        <div id="<?php echo $element->getId(); ?>-errors" class="invalid-feedback d-block"><?php echo join('<br>', $errors); ?></div>
    <?php endif; ?>

    <?php if ($element->getDescription()): ?>
        <div class="form-text"><?php echo $element->getDescription(); ?></div>
    <?php endif; ?>
</div>

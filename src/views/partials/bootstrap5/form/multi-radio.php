<?php
/**
 * @var \Ufw\Form\Element\Radio $element
 * @var \Ufw\Helper\Render $this
 */
echo $this->partial('ufw-form-check-icon-styles-once.php');

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $ord = 1;
?>
<div class="mb-3 <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : ''; ?>">
    <?php if ($element->getLabel()): ?>
        <div class="form-label <?php echo $element->getLabelClass(); ?>"><?php echo $element->getLabel(); ?></div>
    <?php endif;?>

    <?php foreach ($element->getMultiOptions() as $optKey => $optValue): ?>
        <div class="form-check ufw-form-check-decorated">
            <?php
            $optAttribs = $element->getAttribs();
            $optClassAttrib = $optAttribs['class'] ?? '';
            $oc = trim((is_string($optClassAttrib) ? $optClassAttrib : '') . ' form-check-input');
            $optAttribs['class'] = $oc;
            ?>
            <?php echo $this->html5Tag()->void('input', ['value' => $optKey, 'id' => $element->getId() . '-' . $ord, 'checked' => is_string($elementValue = $element->getValue()) && $elementValue == $optKey] + $optAttribs); ?>
            <label class="form-check-label" for="<?php echo $element->getId() . '-' . $ord++; ?>">
                <span class="ufw-form-check-on" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-8 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg></span>
                <span class="ufw-form-check-off" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/></svg></span>
                <?php echo htmlspecialchars(is_string($optValue) ? $optValue : '', ENT_QUOTES | ENT_HTML5); ?>
            </label>
        </div>
    <?php endforeach; ?>

    <?php if ($hasErrors): ?>
        <div id="<?php echo $element->getId(); ?>-errors" class="invalid-feedback d-block"><?php echo join('<br>', $errors); ?></div>
    <?php endif; ?>

    <?php if ($element->getDescription()): ?>
        <div class="form-text"><?php echo $element->getDescription(); ?></div>
    <?php endif; ?>
</div>

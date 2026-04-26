<?php
/**
 * @var \Ufw\Form\Element\CheckboxSet $element
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
            $oc = trim(($optAttribs['class'] ?? '') . ' form-check-input');
            $optAttribs['class'] = $oc;
            ?>
            <?php echo $this->html5Tag()->void('input', ['value' => $optKey, 'id' => $element->getId() . '-' . $ord, 'checked' => @in_array($optKey, $element->getValue())] + $optAttribs); ?>
            <label class="form-check-label" for="<?php echo $element->getId() . '-' . $ord++; ?>">
                <span class="ufw-form-check-on" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2zm10.03 4.97a.75.75 0 0 1 .011 1.05l-3.992 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/></svg></span>
                <span class="ufw-form-check-off" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/></svg></span>
                <?php echo htmlspecialchars($optValue, ENT_QUOTES | ENT_HTML5); ?>
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

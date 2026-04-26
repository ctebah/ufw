<?php
/**
 * @var \Ufw\Form\Element\ReCaptcha $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
?>
<div class="mb-3 <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : ''; ?>">
    <div id="<?php echo $element->getId(); ?>-visual" data-sitekey="" data-callback="recaptchaCallback"></div>
    <input id="<?php echo $element->getId(); ?>" name="<?php echo $element->getAttrib('name'); ?>" value="" required tabindex="-1" style="width:50px; height:0; opacity:0; pointer-events:none; position:absolute; bottom:0;">
    <?php if ($hasErrors): ?>
        <div id="<?php echo $element->getId(); ?>-errors" class="invalid-feedback d-block"><?php echo join('<br>', $errors); ?></div>
    <?php endif; ?>
</div>

<?php $this->placeholder('jsInline')->captureStart(); ?>
<script>
(function() {
    var recaptchaCallback = function(response) {
        $('#<?php echo $element->getId(); ?>').val(response);
    }
    $(document).one('grecaptcha.loaded', function() {
        grecaptcha.render('<?php echo $element->getId(); ?>-visual', {
            sitekey: '<?php echo $element->getOption('site_key'); ?>',
            size: 'normal',
            tabindex: 0,
            callback: recaptchaCallback
        })
    });
    if (typeof grecaptcha == 'object') {
        $(document).trigger('grecaptcha.loaded');
    }
})();
</script>
<?php $this->placeholder('jsInline')->captureEnd(); ?>

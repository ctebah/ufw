<?php
/**
 * @var \Ufw\Form\Element\ReCaptcha $element
 * @var \Ufw\Helper\Render $this
 */

    $errors = $element->getErrors();
    $hasErrors = !!$errors;
    $hasSuccess = @strlen($element->getValue()) && !$hasErrors && $element->getForm()->getValidated();
?>
<div class="form-group <?php echo $element->getWrapClass() . ' ', $element->isRequired() ? 'required ' : '', $hasSuccess ? 'has-success ' : '', $hasErrors ? 'has-error ' : '', $hasErrors || $hasSuccess ? 'has-feedback ' : ''; ?>">
    <div id="<?php echo $element->getId(); ?>-visual" data-sitekey="" data-callback="recaptchaCallback"></div>
    <input id="<?php echo $element->getId(); ?>" name="<?php echo $element->getAttrib('name'); ?>" value="" required tabindex="-1" style="width:50px; height:0; opacity:0; pointer-events:none; position:absolute; bottom:0;">
    <?php if ($hasErrors): ?>
        <span class="glyphicon glyphicon-remove form-control-feedback" aria-hidden="true"></span>
        <span id="<?php echo $element->getId(); ?>-errors" class="form-text text-muted"><?php echo join('<br>', $errors); ?></span>
    <?php endif; ?>
</div>

<?php $this->placeholder('jsInline')->captureStart(); ?>
<script>
(function() {
    var inputId = '<?php echo $element->getId(); ?>';
    var $input = $('#' + inputId);
    var $visual = $('#' + inputId + '-visual');
    var $form = $input.closest('form');

    var omitGoogleRecaptchaField = function() {
        $visual.find('textarea[name="g-recaptcha-response"]').prop('disabled', true);
        if ($form.length) {
            $form.find('textarea[name="g-recaptcha-response"]').prop('disabled', true);
        }
    };

    var recaptchaCallback = function(response) {
        $input.val(response);
        omitGoogleRecaptchaField();
    };

    var recaptchaExpiredCallback = function() {
        $input.val('');
        $visual.find('textarea[name="g-recaptcha-response"]').prop('disabled', false);
    };

    if ($form.length) {
        $form.on('submit', omitGoogleRecaptchaField);
    }

    $(document).one('grecaptcha.loaded', function() {
        grecaptcha.render(inputId + '-visual', {
            sitekey: '<?php echo $element->getOption('site_key'); ?>',
            size: 'normal',
            tabindex: 0,
            callback: recaptchaCallback,
            'expired-callback': recaptchaExpiredCallback
        });
    });
    if (typeof grecaptcha == 'object') {
        $(document).trigger('grecaptcha.loaded');
    }
})();
</script>
<?php $this->placeholder('jsInline')->captureEnd(); ?>

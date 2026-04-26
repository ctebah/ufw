<?php
/**
 * One-shot CSS for Bootstrap 5 form partials only ({@code bootstrap5/form/checkbox.php}, multi-checkbox, multi-radio):
 * toggles {@code .ufw-form-check-on} / {@code .ufw-form-check-off} from the real input {@code :checked} state (inline SVG, no icon font).
 */
if (!empty($GLOBALS['__ufw_form_check_icon_css'])) {
    return;
}
$GLOBALS['__ufw_form_check_icon_css'] = true;
?>
<style id="ufw-form-check-icon-css">
.ufw-form-check-decorated > input[type="checkbox"]:checked ~ label .ufw-form-check-off,
.ufw-form-check-decorated > input[type="radio"]:checked ~ label .ufw-form-check-off {
    display: none !important;
}
.ufw-form-check-decorated > input[type="checkbox"]:not(:checked) ~ label .ufw-form-check-on,
.ufw-form-check-decorated > input[type="radio"]:not(:checked) ~ label .ufw-form-check-on {
    display: none !important;
}
.ufw-form-check-on,
.ufw-form-check-off {
    display: inline-block;
    margin-right: 0.25rem;
    vertical-align: -0.15em;
    line-height: 0;
}
.ufw-form-check-on svg,
.ufw-form-check-off svg {
    display: block;
    width: 1.125em;
    height: 1.125em;
}
/* Native control stays in the DOM for a11y/submit; SVG is the visible affordance. */
.ufw-form-check-decorated {
    position: relative;
}
.ufw-form-check-decorated > input.form-check-input {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}
.ufw-form-check-decorated > input.form-check-input:focus-visible ~ label {
    outline: 2px solid var(--bs-primary, #0d6efd);
    outline-offset: 2px;
    border-radius: 0.25rem;
}
</style>

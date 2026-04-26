<?php
/**
 * @var \Ufw\Form\Element\Csrf $element
 * @var \Ufw\Helper\Render $this
 */
$attribs = $element->getAttribs();
$attribs['type'] = 'hidden';
$attribs['value'] = (string) $element->getValue();
echo $this->html5Tag()->void('input', $attribs);

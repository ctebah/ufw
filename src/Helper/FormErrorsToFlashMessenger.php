<?php

namespace Ufw\Helper;

/**
 * Copies {@see \Ufw\Form\Base} validation errors into flash messages (labels + HTML line breaks), typically
 * after a failed POST for user-visible feedback.
 */
class FormErrorsToFlashMessenger extends Base
{

    public function __invoke(\Ufw\Form\Base $form = null)
    {
        if (!$form || !$form->getErrors()) {
            return;
        }
        foreach ($form->getErrors() as $key => $value) {
            $element = $form->getElement($key);
            $label = $element->getLabel() ? $element->getLabel() : $element->getName();
            $this->flashMessenger()->addMessage(($label ? $label . ': ' : '') . join('<br>', $value), 'error');
        }
    }
}

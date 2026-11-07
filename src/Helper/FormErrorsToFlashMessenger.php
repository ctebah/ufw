<?php

namespace Ufw\Helper;

/**
 * Copies {@see \Ufw\Form\Base} validation errors into flash messages (labels + HTML line breaks), typically
 * after a failed POST for user-visible feedback.
 */
class FormErrorsToFlashMessenger extends Base
{

    /**
     * @param \Ufw\Form\Base|null $form
     * @return void
     */
    public function __invoke(\Ufw\Form\Base $form = null)
    {
        if (!$form || !$form->getErrors()) {
            return;
        }
        foreach ($form->getErrors() as $key => $value) {
            $element = $form->getElement($key);
            if ($element === null) {
                continue;
            }
            $labelOpt = $element->getOption('label');
            $nameAttrib = $element->getAttrib('name');
            $label = '';
            if (is_string($labelOpt) && $labelOpt !== '') {
                $label = $labelOpt;
            } elseif (is_string($nameAttrib) && $nameAttrib !== '') {
                $label = $nameAttrib;
            }
            $this->flashMessenger()->addMessage(($label !== '' ? $label . ': ' : '') . join('<br>', $value), 'error');
        }
    }
}

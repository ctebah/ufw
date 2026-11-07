<?php
namespace Ufw\Helper;

/**
 * Plain Bootstrap 3 form render helper (no Material Icons markers for checkbox/radio controls).
 *
 * Uses stock `bootstrap3/` partials for all elements except:
 * - checkbox
 * - multi-checkbox
 * - multi-radio
 *
 * Those three are served from `bootstrap3plain/`.
 */
class Bootstrap3plain extends FormRenderHelperBase
{
    protected $baseScriptPath = 'bootstrap3';

    /**
     * @param string|null $script
     * @return string
     */
    public function field(\Ufw\Form\Element\Base $element, $script = null)
    {
        if (!$script) {
            if ($element instanceof \Ufw\Form\Element\Checkbox) {
                $script = 'bootstrap3plain/form/checkbox.php';
            } elseif ($element instanceof \Ufw\Form\Element\CheckboxSet) {
                $script = 'bootstrap3plain/form/multi-checkbox.php';
            } elseif ($element instanceof \Ufw\Form\Element\Radio) {
                $script = 'bootstrap3plain/form/multi-radio.php';
            } else {
                $script = $element->getRenderScript();
                if (strpos($script, 'bootstrap3plain/') === 0) {
                    $script = 'bootstrap3/' . substr($script, strlen('bootstrap3plain/'));
                }
            }
        }

        if ($element->getMetadata()) {
            $this->placeholder('jsReady')->append($this->partial("{$this->baseScriptPath}/form/_metadata.php", [
                'element' => $element
            ]));
        }

        return $this->partial($script, [
            'element' => $element
        ]);
    }
}

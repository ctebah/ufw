<?php
namespace Ufw\Helper;

/**
 * Plain Bootstrap 4 form render helper (no Material Icons markers for checkbox/radio controls).
 *
 * Uses stock `bootstrap4/` partials for all elements except:
 * - checkbox
 * - multi-checkbox
 * - multi-radio
 *
 * Those three are served from `bootstrap4plain/`.
 */
class Bootstrap4plain extends FormRenderHelperBase
{
    protected $baseScriptPath = 'bootstrap4';

    public function field(\Ufw\Form\Element\Base $element, $script = null)
    {
        if (!$script) {
            if ($element instanceof \Ufw\Form\Element\Checkbox) {
                $script = 'bootstrap4plain/form/checkbox.php';
            } elseif ($element instanceof \Ufw\Form\Element\CheckboxSet) {
                $script = 'bootstrap4plain/form/multi-checkbox.php';
            } elseif ($element instanceof \Ufw\Form\Element\Radio) {
                $script = 'bootstrap4plain/form/multi-radio.php';
            } else {
                $script = $element->getRenderScript();
                if (strpos($script, 'bootstrap4plain/') === 0) {
                    $script = 'bootstrap4/' . substr($script, strlen('bootstrap4plain/'));
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

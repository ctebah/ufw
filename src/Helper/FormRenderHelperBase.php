<?php
namespace Ufw\Helper;

/**
 * Base class for form render helpers (see {@see \Ufw\Form\Base::getRenderHelperName()} and config
 * `form.helper`). Implements shared `form` / `field` rendering from a partial subtree
 * ({@see $baseScriptPath}); concrete subclasses only pick the folder under `views/partials/`.
 */
abstract class FormRenderHelperBase extends Base
{
    /**
     * Subfolder under `views/partials/` for this helper's form partials.
     *
     * @var string
     */
    protected $baseScriptPath;

    /**
     * @return $this
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * @return string
     */
    public function form(\Ufw\Form\Base $form)
    {
        $viewScript = $form->getViewScript();
        if ($viewScript) {
            $out = $this->partial($viewScript, [
                'form' => $form
            ]);
            $this->placeholder('jsReady')->append($this->partial($viewScript . '.js.php', [
                'form' => $form
            ]));
        } else {
            $baseClassName = (new \ReflectionClass($form))->getShortName();
            $out = $this->partial("{$this->baseScriptPath}/form/form.php", [
                'form' => $form
            ]);
            $this->placeholder('jsReady')->append($this->partial("form/$baseClassName.js.php", [
                'form' => $form
            ]));
        }
        return $out;
    }

    /**
     * @param string|null $script
     * @return string
     */
    public function field(\Ufw\Form\Element\Base $element, $script = null)
    {
        if (!$script) {
            $script = $element->getRenderScript();
        }
        if (!$script) {
            $script = 'input.php';
            if ($element instanceof \Ufw\Form\Element\Csrf) {
                $script = 'csrf.php';
            } elseif ($element instanceof \Ufw\Form\Element\Input) {
                $script = 'input.php';
            } elseif ($element instanceof \Ufw\Form\Element\Multi) {
                $script = 'multi-' . $element->getType() . '.php';
            } else {
                $script = (new \ReflectionClass($element))->getShortName() . '.php';
            }
            $script = "{$this->baseScriptPath}/form/$script";
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

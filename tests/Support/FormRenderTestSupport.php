<?php
namespace Ufw\Tests\Support;

trait FormRenderTestSupport
{
    protected function resetFormRenderHelperNames(): void
    {
        foreach (array(\Ufw\Form\Base::class, \Ufw\Form\Element\Base::class) as $class) {
            $prop = new \ReflectionProperty($class, 'renderHelperName');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    /**
     * @param string $helperName e.g. bootstrap3, bootstrap4, bootstrap5
     */
    protected function syncFormRenderHelperName($helperName): void
    {
        \Ufw\Form\Base::setRenderHelperName($helperName);
        \Ufw\Form\Element\Base::setRenderHelperName($helperName);
    }
}

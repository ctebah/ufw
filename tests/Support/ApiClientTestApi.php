<?php
namespace Ufw\Tests\Support;

use Ufw\Component\ApiClient\Exception as ApiException;

class ApiClientTestApi extends \Ufw\Component\ApiClient\Base
{
    public static function resetInstances()
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Base::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
    }

    public function getFieldsMap()
    {
        return array('title' => 'title');
    }

    public function exposeGetApplication()
    {
        return $this->getApplication();
    }

    public function exposeRun($func)
    {
        try {
            return $this->_run($func);
        } catch (ApiException $e) {
            return $this;
        }
    }

    public function exposeSetFormErrors(array $errors)
    {
        $method = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, 'setFormErrors');
        $method->setAccessible(true);

        return $method->invoke($this, $errors);
    }

    /**
     * @var \Ufw\Component\ApiClient\Helper|null
     */
    public $helperOverride;

    /**
     * @return \Ufw\Component\ApiClient\Helper
     */
    public function getHelper()
    {
        if ($this->helperOverride instanceof \Ufw\Component\ApiClient\Helper) {
            return $this->helperOverride;
        }

        return parent::getHelper();
    }
}

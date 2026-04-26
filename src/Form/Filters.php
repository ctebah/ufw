<?php
namespace Ufw\Form;

/**
 * Input filter chain on elements: {@see Filter\Base} instances or short names resolved to `Form\Filter\*` / `Ufw\Form\Filter\*`.
 */
trait Filters {

    /**
     *
     * @var \Ufw\Form\Collection
     */
    protected $filters = null;

    /**
     *
     * @return \Ufw\Form\Collection
     */
    protected function filters()
    {
        if (!isset($this->filters)) {
            $this->filters = new Collection();
        }
        return $this->filters;
    }

    public function getFilter($name)
    {
        $filter = $this->filters()->get($name);
        if ($filter instanceof \Ufw\Form\Filter\Base) {
            return $filter;
        }
        if (is_string($filter)) {
            $class = 'Form\\Filter\\' . ucfirst($filter);
            if (!class_exists($class)) {
                $class = 'Ufw\\Form\\Filter\\' . ucfirst($filter);
            }
            $filter = new $class();
            $this->setFilter($name, $filter);
            return $filter;
        }
        if (is_array($filter)) {
            $class = 'Form\\Filter\\' . ucfirst(isset($filter['name']) && $filter['name'] ? $filter['name'] : $name);
            if (!class_exists($class)) {
                $class = 'Ufw\\Form\\Filter\\' . ucfirst(isset($filter['name']) && $filter['name'] ? $filter['name'] : $name);
            }
            $r = new \ReflectionClass($class);
            if ($r->hasMethod('__construct')) {
                $filter = $r->newInstanceArgs((array) $filter['options']);
            } else {
                $filter = $r->newInstance();
            }
            $this->setFilter($name, $filter);
            return $filter;
        }
    }

    public function addFilter($name, $value = null)
    {
        if ($name instanceof \Ufw\Form\Filter\Base) {
            $value = $name;
            $name = $value->getName();
        } elseif (is_array($name)) {
            $value = $name;
            $name = $name['name'];
        }
        if (!$value) {
            $value = $name;
        }
        $this->filters()->set($name, $value);
        return $this;
    }

    public function setFilter($name, $value)
    {
        $this->filters()->set($name, $value);
        return $this;
    }

    public function hasFilter($name)
    {
        return $this->filters()->has($name);
    }

    public function removeFilter($name)
    {
        return $this->filters()->remove($name);
    }

    /**
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->filters()->getAll();
    }

    public function getFilterNames()
    {
        return array_keys($this->getFilters());
    }

    /**
     *
     * @param array $values
     */
    public function setFilters($values)
    {
        return $this->filters()->setAll($values);
    }
}
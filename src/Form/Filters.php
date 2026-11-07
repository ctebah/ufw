<?php
namespace Ufw\Form;

/**
 * Input filter chain on elements: {@see Filter\Base} instances or short names resolved to `Form\Filter\*` / `Ufw\Form\Filter\*`.
 */
trait Filters {

    /**
     * @var \Ufw\Form\Collection|null
     */
    protected $filters = null;

    /**
     * @return \Ufw\Form\Collection
     */
    protected function filters()
    {
        if ($this->filters === null) {
            $this->filters = new Collection();
        }
        return $this->filters;
    }

    /**
     * @param string $name
     * @return \Ufw\Form\Filter\Base|null
     */
    public function getFilter($name)
    {
        $filter = $this->filters()->get($name);
        if ($filter instanceof \Ufw\Form\Filter\Base) {
            return $filter;
        }
        if (is_string($filter)) {
            $class = $this->resolveFilterClass($filter);
            $filter = new $class();
            $this->setFilter($name, $filter);
            return $filter;
        }
        if (is_array($filter)) {
            $filterName = $name;
            if (isset($filter['name']) && is_string($filter['name']) && $filter['name'] !== '') {
                $filterName = $filter['name'];
            }
            $class = $this->resolveFilterClass($filterName);
            $r = new \ReflectionClass($class);
            if ($r->hasMethod('__construct')) {
                $filter = $r->newInstanceArgs((array) ($filter['options'] ?? []));
            } else {
                $filter = $r->newInstance();
            }
            $this->setFilter($name, $filter);
            return $filter;
        }

        return null;
    }

    /**
     * @param string $shortName
     * @return class-string<\Ufw\Form\Filter\Base>
     */
    protected function resolveFilterClass($shortName)
    {
        $class = 'Form\\Filter\\' . ucfirst($shortName);
        if (!class_exists($class)) {
            $class = 'Ufw\\Form\\Filter\\' . ucfirst($shortName);
        }
        if (!class_exists($class) || !is_subclass_of($class, \Ufw\Form\Filter\Base::class)) {
            throw new \InvalidArgumentException(sprintf('Unknown form filter class for "%s".', $shortName));
        }

        return $class;
    }

    /**
     * @param \Ufw\Form\Filter\Base|array<string, mixed>|string $name
     * @param \Ufw\Form\Filter\Base|array<string, mixed>|string|null $value
     * @return $this
     */
    public function addFilter($name, $value = null)
    {
        if ($name instanceof \Ufw\Form\Filter\Base) {
            $value = $name;
            $name = $value->getName();
        } elseif (is_array($name)) {
            $value = $name;
            if (isset($name['name']) && is_string($name['name']) && $name['name'] !== '') {
                $name = $name['name'];
            } else {
                throw new \InvalidArgumentException('Filter definition must include a non-empty string "name".');
            }
        }
        if (!$value) {
            $value = $name;
        }
        $this->filters()->set($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setFilter($name, $value)
    {
        $this->filters()->set($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasFilter($name)
    {
        return $this->filters()->has($name);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function removeFilter($name)
    {
        return $this->filters()->remove($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters()
    {
        return $this->filters()->getAll();
    }

    /**
     * @return list<string>
     */
    public function getFilterNames()
    {
        return array_keys($this->getFilters());
    }

    /**
     * @param array<string, mixed> $values
     * @return $this
     */
    public function setFilters(array $values)
    {
        $this->filters()->setAll($values);
        return $this;
    }
}

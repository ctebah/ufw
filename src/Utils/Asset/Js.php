<?php
namespace Ufw\Utils\Asset;

/**
 * Renders `<script src="…">` tags from manifest `cdn` / `local` arrays, filtered by {@see $position} ({@see POS_HEAD}, …).
 */
class Js extends Base
{

    const POS_HEAD = 'head';

    const POS_BODY_BEGIN = 'body-begin';

    const POS_BODY_BOTTOM = 'body-bottom';

    const POS_BODY_END = 'body-end';

    protected $position = self::POS_BODY_BOTTOM;

    /**
     *
     * @return string
     */
    protected function getCompiled()
    {
        if (!isset($this->compiled)) {
            $buf = [];
            $last = [];
            $data = $this->getData();
            if (isset($data['cdn']) && is_array($data['cdn'])) {
                foreach ($data['cdn'] as $resource) {
                    $resource = $this->getResourceStruct($resource);
                    if (strcmp($resource['position'], $this->getPosition())) {
                        continue;
                    }
                    $buf[] = $this->getApplication()
                        ->getHelper('html5Tag')
                        ->full('script', $this->getTagAttribs($resource), '');
                }
            }
            if (isset($data['local']) && is_array($data['local'])) {
                if (!strcmp(self::POS_BODY_BOTTOM, $this->getPosition())) {
                    foreach ($data['local'] as $resource) {
                        if (is_array($resource) && !isset($resource['src'])) {
                            foreach ($resource as $resourceSingle) {
                                $resourceSingle = $this->getResourceStruct($resourceSingle);
                                $buf[] = $this->getApplication()
                                    ->getHelper('html5Tag')
                                    ->full('script', $this->getTagAttribs($resourceSingle), '');
                            }
                        } else {
                            $resource = $this->getResourceStruct($resource);
                            $buf[] = $this->getApplication()
                                ->getHelper('html5Tag')
                                ->full('script', $this->getTagAttribs($resource), '');
                        }
                    }
                }
            }
            $this->compiled = join('', array_merge($buf, $last));
        }
        return $this->compiled;
    }

    protected function getResourceStruct($resource)
    {
        if (is_string($resource)) {
            $resource = [
                'src' => $resource
            ];
        }
        $resource = $resource + [
            'position' => self::POS_BODY_BOTTOM,
            'attribs' => []
        ];
        return $resource;
    }

    protected function getTagAttribs($resource)
    {
        return array_merge($resource['attribs'], [
            'src' => $resource['src']
        ]);
    }

    /**
     *
     * @return string
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     *
     * @param string $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }
}
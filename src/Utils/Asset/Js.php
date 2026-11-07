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

    /**
     * @var self::POS_*|string
     */
    protected $position = self::POS_BODY_BOTTOM;

    /**
     *
     * @return string
     */
    protected function getCompiled()
    {
        if ($this->compiled === null) {
            $buf = [];
            $last = [];
            $data = $this->getData();
            if ($data === null) {
                return '';
            }
            if (isset($data['cdn']) && is_array($data['cdn'])) {
                foreach ($data['cdn'] as $resource) {
                    if (!is_string($resource) && !is_array($resource)) {
                        continue;
                    }
                    $resource = $this->getResourceStruct($resource);
                    if (strcmp($resource['position'], $this->getPosition())) {
                        continue;
                    }
                    $buf[] = $this->getHtml5TagHelper()
                        ->full('script', $this->getTagAttribs($resource), '');
                }
            }
            if (isset($data['local']) && is_array($data['local'])) {
                if (!strcmp(self::POS_BODY_BOTTOM, $this->getPosition())) {
                    foreach ($data['local'] as $resource) {
                        if (!is_string($resource) && !is_array($resource)) {
                            continue;
                        }
                        if (is_array($resource) && !isset($resource['src'])) {
                            foreach ($resource as $resourceSingle) {
                                if (!is_string($resourceSingle) && !is_array($resourceSingle)) {
                                    continue;
                                }
                                $resourceSingle = $this->getResourceStruct($resourceSingle);
                                $buf[] = $this->getHtml5TagHelper()
                                    ->full('script', $this->getTagAttribs($resourceSingle), '');
                            }
                            continue;
                        }
                        $resource = $this->getResourceStruct($resource);
                        $buf[] = $this->getHtml5TagHelper()
                            ->full('script', $this->getTagAttribs($resource), '');
                    }
                }
            }
            $this->compiled = join('', array_merge($buf, $last));
        }
        return $this->compiled ?? '';
    }

    /**
     * @param string|array<int|string, mixed> $resource
     * @return array{src: string, position: string, attribs: array<string, mixed>}
     */
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
        $src = isset($resource['src']) && (is_string($resource['src']) || is_int($resource['src'])) ? (string) $resource['src'] : '';
        $position = isset($resource['position']) && (is_string($resource['position']) || is_int($resource['position']))
            ? (string) $resource['position']
            : self::POS_BODY_BOTTOM;
        return [
            'src' => $src,
            'position' => $position,
            'attribs' => is_array($resource['attribs']) ? \Ufw\filter_string_keyed_array($resource['attribs']) : [],
        ];
    }

    /**
     * @param array{src: string, position: string, attribs: array<string, mixed>} $resource
     * @return array<string, mixed>
     */
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
     * @param self::POS_*|string $position
     * @return $this
     */
    public function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }
}
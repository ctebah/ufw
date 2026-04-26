<?php
namespace Ufw\Utils\Asset;

/**
 * Renders `<link rel="stylesheet">` tags from manifest `cdn` / `local` href lists.
 */
class Css extends Base
{

    /**
     *
     * @return string
     */
    protected function getCompiled()
    {
        if (!isset($this->compiled)) {
            $buf = [];
            $data = $this->getData();
            if (isset($data['cdn']) && is_array($data['cdn'])) {
                foreach ($data['cdn'] as $resource) {
                    $buf[] = $this->getApplication()
                        ->getHelper('html5Tag')
                        ->void('link', [
                        'href' => $resource,
                        'media' => 'all',
                        'rel' => 'stylesheet',
                        'type' => 'text/css'
                    ]);
                }
            }
            if (isset($data['local']) && is_array($data['local'])) {
                foreach ($data['local'] as $resource) {
                    if (is_array($resource)) {
                        foreach ($resource as $resourceSingle) {
                            $buf[] = $this->getApplication()
                                ->getHelper('html5Tag')
                                ->void('link', [
                                'href' => $resource,
                                'media' => 'all',
                                'rel' => 'stylesheet',
                                'type' => 'text/css'
                            ]);
                        }
                    } else {
                        $buf[] = $this->getApplication()
                            ->getHelper('html5Tag')
                            ->void('link', [
                            'href' => $resource,
                            'media' => 'all',
                            'rel' => 'stylesheet',
                            'type' => 'text/css'
                        ]);
                    }
                }
            }
            $this->compiled = join('', $buf);
        }
        return $this->compiled;
    }
}
<?php
namespace Ufw\Controller;

/**
 * JSON-first API controller: sets {@see \Ufw\Response} format to `json`, avoids overwriting render output, and
 * prepends {@see \Ufw\Middleware\JsonRequest} so request bodies are parsed for API actions.
 */
abstract class RestApi extends Base
{

    /**
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->getResponse()->setFormat('json');
        $render = $this->getHelper('render');
        if ($render instanceof \Ufw\Helper\Render) {
            $render->setOverwrite(false);
        }
    }

    /**
     * @return list<class-string>
     */
    protected function middlewaresPre()
    {
        return [
            \Ufw\Middleware\JsonRequest::class,
        ];
    }
}

<?php

namespace Ufw\Helper;

/**
 * Reloads the current URL by delegating to the {@see Redirect} helper with the active request target.
 */
class Reload extends Base
{

    /**
     * @return void
     */
    public function __invoke()
    {
        $this->reload();
    }

    /**
     * @return void
     */
    public function reload()
    {
        $this->redirect($this->getRequest()->getRequestTarget());
    }
}

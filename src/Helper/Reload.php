<?php

namespace Ufw\Helper;

/**
 * Reloads the current URL by delegating to the {@see Redirect} helper with the active request target.
 */
class Reload extends Base
{

    public function __invoke()
    {
        $this->reload();
    }

    public function reload()
    {
        $this->redirect($this->getRequest()->getRequestTarget());
    }
}

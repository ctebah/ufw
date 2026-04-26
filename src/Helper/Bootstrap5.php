<?php
namespace Ufw\Helper;

/**
 * Form render helper backed by the `bootstrap5/` partial tree under `views/partials/` (see {@see FormRenderHelperBase}).
 */
class Bootstrap5 extends FormRenderHelperBase
{
    protected $baseScriptPath = 'bootstrap5';
}

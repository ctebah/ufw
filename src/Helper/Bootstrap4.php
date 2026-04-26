<?php
namespace Ufw\Helper;

/**
 * Form render helper backed by the `bootstrap4/` partial tree under `views/partials/` (see {@see FormRenderHelperBase}).
 */
class Bootstrap4 extends FormRenderHelperBase
{
    protected $baseScriptPath = 'bootstrap4';
}

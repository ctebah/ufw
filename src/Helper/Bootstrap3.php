<?php
namespace Ufw\Helper;

/**
 * Form render helper backed by the `bootstrap3/` partial tree under `views/partials/` (see {@see FormRenderHelperBase}).
 */
class Bootstrap3 extends FormRenderHelperBase
{
    protected $baseScriptPath = 'bootstrap3';
}

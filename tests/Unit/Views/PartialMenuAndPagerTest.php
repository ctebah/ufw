<?php
namespace Ufw\Tests\Unit\Views;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Ufw\Form\Element\Checkbox;
use Ufw\Form\Element\CheckboxSet;
use Ufw\Form\Element\DateRange;
use Ufw\Form\Element\Html;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Form\Element\Textarea;
use Ufw\Helper\Bootstrap3;
use Ufw\Helper\Bootstrap3plain;
use Ufw\Helper\Bootstrap4;
use Ufw\Helper\Bootstrap4plain;
use Ufw\Helper\Bootstrap5;
use Ufw\Helper\FormRenderHelperBase;
use Ufw\Helper\Partial;
use Ufw\Paths;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormRenderTestSupport;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\TestHttpRequest;
use Ufw\Tests\Support\PaginatorControllerStub;

/**
 * Targets remaining line gaps in bundled view partials under src/views/partials/.
 */
class PartialMenuAndPagerTest extends TestCase
{
    use FormFixtureConfig;
    use FormRenderTestSupport;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        unset($GLOBALS['__ufw_form_check_icon_css']);
        $this->resetFormRenderHelperNames();
        parent::tearDown();
    }

    /**
     * @return list<array{0: string, 1: class-string<FormRenderHelperBase>}>
     */
    public static function formRenderHelpers(): array
    {
        return array(
            array('bootstrap3', Bootstrap3::class),
            array('bootstrap4', Bootstrap4::class),
            array('bootstrap4plain', Bootstrap4plain::class),
            array('bootstrap5', Bootstrap5::class),
            array('bootstrap3plain', Bootstrap3plain::class),
        );
    }

    public function testBreadcrumbsAndMenuCaptionEdgeBranches(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $partial = new Partial();
        $partial->setController($controller);

        $edgeItems = array(
            array('caption' => function () {
                return 'CallableString';
            }, 'content' => array('href' => '/a')),
            array('caption' => function () {
                return array('not-scalar');
            }, 'content' => array('href' => '/b')),
            array('caption' => new \stdClass(), 'content' => array('href' => '/c')),
        );

        foreach (array('breadcrumbs.php', 'bootstrap4/breadcrumbs.php', 'bootstrap5/breadcrumbs.php') as $script) {
            $html = $partial->partial($script, array('items' => $edgeItems));
            $this->assertStringContainsString('CallableString', $html, $script);
        }

        $menuHtml = $partial->partial('menu-generic.php', array(
            'currentUrl' => '/x',
            'items' => array(
                0 => array(
                    'caption' => function () {
                        return array('x');
                    },
                ),
                1 => array(
                    'caption' => new class() {
                        public function __toString()
                        {
                            return 'ObjMenu';
                        }
                    },
                ),
                2 => array(
                    'caption' => 'Plain',
                    'content' => array('href' => '/plain'),
                ),
                3 => array(
                    'caption' => new \stdClass(),
                ),
            ),
        ));
        $this->assertStringContainsString('Plain', $menuHtml);
        $this->assertStringContainsString('ObjMenu', $menuHtml);
    }

    public function testPaginatorPartialsCoverDefaultAndDisabledBranches(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $controller = new PaginatorControllerStub($app);
        $app->setCurrentController($controller);
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $router = new Router($app, $app->getRequest(), array(
            'default' => array(
                'type' => Literal::class,
                'path' => '/list',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $router->setCurrentRouteName('default');
        $app->setRouter($router);

        $partial = new Partial();
        $partial->setController($controller);

        $this->assertSame('', trim($partial->partial('paginator.php', array('count' => 'x'))));
        $this->assertSame('', trim($partial->partial('bootstrap4/paginator.php', array('count' => 'nope'))));
        $this->assertSame('', trim($partial->partial('bootstrap4/paginator.php', array('count' => 5, 'page' => 1, 'perPage' => 10))));
        $this->assertSame('', trim($partial->partial('paginator.php', array('count' => 8, 'page' => 1, 'perPage' => 10))));

        $generic = $partial->partial('paginator.php', array('count' => 200, 'page' => 0, 'perPage' => 0));
        $this->assertStringContainsString('pagination', $generic);
        $this->assertStringContainsString('disabled', $generic);

        $mid = $partial->partial('paginator.php', array('count' => 200, 'page' => 5, 'perPage' => 25));
        $this->assertStringContainsString('pagination', $mid);

        $b4First = $partial->partial('bootstrap4/paginator.php', array('count' => 200, 'page' => 1, 'perPage' => 10));
        $this->assertStringContainsString('disabled', $b4First);

        $b5Last = $partial->partial('bootstrap5/paginator.php', array(
            'count' => 200,
            'page' => 20,
            'perPage' => 10,
            'routeName' => 'default',
        ));
        $this->assertStringContainsString('pagination', $b5Last);
        $this->assertStringContainsString('disabled', $b5Last);

        $b5FirstSpan = $partial->partial('bootstrap5/paginator.php', array('count' => 200, 'page' => 1, 'perPage' => 10));
        $this->assertStringContainsString('page-link', $b5FirstSpan);

        $b5Defaults = $partial->partial('bootstrap5/paginator.php', array('count' => 200, 'page' => 0, 'perPage' => 0));
        $this->assertStringContainsString('pagination', $b5Defaults);

        $b4Last = $partial->partial('bootstrap4/paginator.php', array('count' => 200, 'page' => 20, 'perPage' => 10));
        $this->assertStringContainsString('disabled', $b4Last);
    }

    #[DataProvider('formRenderHelpers')]
    public function testFormPartialFeedbackDescriptionAndSelectBranches($label, $helperClass): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $this->syncFormRenderHelperName($label);
        /** @var FormRenderHelperBase $helper */
        $helper = new $helperClass();
        $helper->setController($controller);

        $form = new class(array('action' => '/post')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $form->setValidated(true);

        $textIcon = new Text('lineIcon', array('label' => 'Line', 'icon' => '<em class="field-icon">*</em>'));
        $textIcon->setForm($form);
        $textIcon->setValue('filled');
        $textIcon->setLabelPlacement('above');
        $this->assertStringContainsString('field-icon', $helper->field($textIcon));

        $area = new Textarea('notes', array('label' => 'Notes'));
        $area->setForm($form);
        $area->setValue('body');
        $area->setDescription('Notes help');
        $this->assertStringContainsString('notes help', strtolower($helper->field($area)));

        $cb = new Checkbox('agree', array('label' => 'Agree'));
        $cb->setForm($form);
        $cb->setValue('1');
        $cb->setChecked(true);
        $cb->setDescription('Must agree');
        $this->assertStringContainsString('must agree', strtolower($helper->field($cb)));

        $set = new CheckboxSet('tags', array('label' => 'Tags', 'multiOptions' => array('t1' => 'T1', 't2' => 'T2')));
        $set->setForm($form);
        $set->setValue(array('t1'));
        $set->setDescription('Pick tags');
        $this->assertStringContainsString('pick tags', strtolower($helper->field($set)));

        $range = new DateRange('period', array('label' => 'Period', 'separator' => '–'));
        $range->setForm($form);
        $range->setValue(array('from' => '2024-01-01', 'to' => '2024-01-31'));
        $range->setDescription('Date range help');
        $this->assertStringContainsString('date range help', strtolower($helper->field($range)));

        $html = new Html('blurb', array('value' => '<p>Static</p>', 'label' => 'Blurb'));
        $html->setForm($form);
        $html->setValue('<p>Static</p>');
        $html->setLabelPlacement('below');
        $html->setDescription('Html help');
        $this->assertStringContainsString('html help', strtolower($helper->field($html)));

        $singleSelect = new Select('role', array('label' => 'Role', 'multiOptions' => array('a' => 'A', 'b' => 'B')));
        $singleSelect->setForm($form);
        $singleSelect->setValue('a');
        $singleSelect->setDescription('Role help');
        $this->assertNotSame('', trim($helper->field($singleSelect)));

        $multiSelect = new Select('roles', array(
            'label' => 'Roles',
            'multiOptions' => array('a' => 'A', 'b' => 'B'),
        ));
        $multiSelect->setForm($form);
        $multiSelect->setAllowMultiple(true);
        $multiSelect->setValue(array('a', 'b'));
        $multiSelect->setLabelPlacement('below');
        $this->assertNotSame('', trim($helper->field($multiSelect)));
    }

    public function testBootstrap5MetadataPartialAndJsReadySlot(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list($app, $controller) = ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $this->syncFormRenderHelperName('bootstrap5');
        $bs5 = new Bootstrap5();
        $bs5->setController($controller);

        $form = new class(array('action' => '/post')) extends \Ufw\Form\Base {
            public function init()
            {
            }
        };
        $el = new Text('metaField', array('label' => 'Meta', 'metadata' => array('widget' => 'date')));
        $el->setForm($form);
        $bs5->field($el);

        $jsReady = $bs5->placeholder('jsReady');
        $script = (string) $jsReady;
        $this->assertStringContainsString('__ufwElementMeta', $script);
        $this->assertStringContainsString('meta-field-form-element', $script);

        $partial = new Partial();
        $partial->setController($controller);
        $direct = $partial->partial('bootstrap5/form/_metadata.php', array('element' => $el));
        $this->assertStringContainsString('__ufwElementMeta', $direct);
    }
}

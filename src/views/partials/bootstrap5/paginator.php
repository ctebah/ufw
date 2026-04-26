<?php
/**
 * @var int $count
 * @var int $page current page
 * @var int $perPage items per page
 * @var int $pagesToShow number of pages to show
 * @var \Ufw\Helper\Render $this
 */

$page = (int) $page;
$perPage = (int) $perPage;
$pagesToShow = isset($pagesToShow) ? (int) $pagesToShow : 0;
$c = $this->getController();
if (!isset($routeName)) {
    $routeName = 'default';
}

if ($page < 1) {
    $page = 1;
}
if ($perPage < 1) {
    $perPage = $c::PER_PAGE;
}
if ($pagesToShow < 1) {
    $pagesToShow = 11;
}

$totalPages = ceil($count / $perPage);
if ($totalPages <= 1) {
    return;
}

$hasGotoFirst = $page > 1;
$hasGotoLast = $page < $totalPages;

$params = $perPage == $c::PER_PAGE ? [] : [
    'perPage' => $perPage
];

$lo = max(1, floor($page - ($pagesToShow / 2)));
$hi = min($totalPages, $lo + $pagesToShow);
$pagesToShowXS = 4;
$loXSHidden = max(1, floor($page - ($pagesToShowXS / 2)));
$hiXSHidden = min($totalPages, $loXSHidden + $pagesToShowXS);
?>
<nav aria-label="<?php echo htmlspecialchars($this->translate('Page navigation'), ENT_QUOTES, 'UTF-8'); ?>" class="d-inline-block">
    <ul class="pagination">
        <li class="page-item <?php echo $hasGotoFirst ? '' : 'disabled'; ?>">
            <?php if ($hasGotoFirst): ?>
                <a href="<?php echo $this->url($params + ['page' => 1], false, true, $routeName); ?>" class="page-link">«</a>
            <?php else: ?>
                <span class="page-link">«</span>
            <?php endif; ?>
        </li>
        <?php for ($i = $lo; $i <= $hi; $i++): ?>
            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?> <?php echo($i < $loXSHidden) || ($i > $hiXSHidden) ? 'd-none d-sm-inline-flex' : ''; ?>">
                <a href="<?php echo $this->url($params + ['page' => $i], false, true, $routeName); ?>" class="page-link"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $hasGotoLast ? '' : 'disabled'; ?>">
            <?php if ($hasGotoLast): ?>
                <a href="<?php echo $this->url($params + ['page' => $totalPages], false, true, $routeName); ?>" class="page-link">»</a>
            <?php else: ?>
                <span class="page-link">»</span>
            <?php endif; ?>
        </li>
    </ul>
</nav>

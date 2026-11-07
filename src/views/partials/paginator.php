<?php
/**
 * @var mixed $count
 * @var \Ufw\Helper\Render $this
 */

if (!isset($count) || !is_numeric($count)) {
    return;
}
$count = (int) $count;
$page = (isset($page) && is_numeric($page)) ? (int) $page : 1;
$perPage = (isset($perPage) && is_numeric($perPage)) ? (int) $perPage : 0;
$pagesToShow = (isset($pagesToShow) && is_numeric($pagesToShow)) ? (int) $pagesToShow : 0;
$c = $this->getController();

if ($page < 1) {
    $page = 1;
}
if ($perPage < 1) {
    $defaultPerPage = $c::PER_PAGE;
    $perPage = is_numeric($defaultPerPage) ? (int) $defaultPerPage : 10;
}
if ($pagesToShow < 1) {
    $pagesToShow = 11;
}

$totalPages = ceil($count / $perPage);
if ($totalPages <= 1)
    return;

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
<ul class="pagination pagination-lg">
    <li class="prev <?php echo $hasGotoFirst ? '' : 'disabled'; ?>"><a href="<?php echo $this->url($params + ['page' => 1], false, true); ?>">«</a></li>
<?php
    for ($i = $lo; $i <= $hi; $i++) {
?>
    <li class="<?php echo $page == $i ? 'active' : ''; ?> <?php echo ($i < $loXSHidden) || ($i > $hiXSHidden) ? 'hidden-xs' : ''; ?>">
    	<a href="<?php echo $this->url($params + ['page' => $i], false, true); ?>"><?php echo $i; ?></a>
	</li>
<?php
    }
?>
    <li class="last <?php echo $hasGotoLast ? '' : 'disabled'; ?>"><a href="<?php echo $this->url($params + ['page' => $totalPages], false, true); ?>">»</a></li>
</ul>
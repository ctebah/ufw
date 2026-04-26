<?php
/**
 * @var array $items
 */
?>

<?php
    end($items);
    $lastKey = key($items);
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <?php foreach ($items as $key => $item): ?>
            <?php if ($key == $lastKey): ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $item['caption']; ?></li>
            <?php else: ?>
                <li class="breadcrumb-item"><a href="<?php echo $item['content']['href']; ?>"><?php echo $item['caption']; ?></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>

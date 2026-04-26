<?php 
/**
 * @var array $items
 */
?>

<?php 
    end($items);
    $lastKey = key($items);
?>
<ol class="breadcrumb">
    <?php foreach ($items as $key => $item): ?>
        <?php if ($key == $lastKey): ?>
            <li class="active"><?php echo $item['caption']; ?></li>
        <?php else: ?>
            <li><a href="<?php echo $item['content']['href']; ?>"><?php echo $item['caption']; ?></a></li>
        <?php endif; ?>
    <?php endforeach; ?>
</ol>
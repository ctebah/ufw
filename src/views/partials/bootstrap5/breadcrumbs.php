<?php
/**
 * @var array<int|string, mixed> $items
 */
?>

<?php
    end($items);
    $lastKey = key($items);
    $breadcrumbCaption = function ($caption) {
        if (is_string($caption)) {
            return $caption;
        }
        if (is_callable($caption)) {
            $resolved = $caption();
            if (is_string($resolved)) {
                return $resolved;
            }
            if (is_scalar($resolved)) {
                return (string) $resolved;
            }
            return '';
        }
        if (is_object($caption) && method_exists($caption, '__toString')) {
            return (string) $caption;
        }
        return '';
    };
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <?php foreach ($items as $key => $item): ?>
            <?php if (!is_array($item)) { continue; } ?>
            <?php $caption = isset($item['caption']) ? $breadcrumbCaption($item['caption']) : ''; ?>
            <?php if ($key == $lastKey): ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $caption; ?></li>
            <?php else: ?>
                <?php $href = isset($item['content']) && is_array($item['content']) && isset($item['content']['href']) && is_string($item['content']['href']) ? $item['content']['href'] : ''; ?>
                <li class="breadcrumb-item"><a href="<?php echo $href; ?>"><?php echo $caption; ?></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>

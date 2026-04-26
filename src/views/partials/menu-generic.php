<?php

/**
 * @var array $items
 * @var string $currentUrl
 * @var \Ufw\Helper\Partial $this
 */
$wrapper = \Ufw\array_get_and_unset($items, 'wrapper');

foreach ($wrapper as $wrapItem) {
    if ($wrapItem['tag']) {
        echo $this->html5Tag()->open($wrapItem['tag'], $wrapItem['attribs']);
    }
}

foreach ($items as $key => $item) {
    if (!is_numeric($key)) {
        continue;
    }
    
    foreach ($item['wrapper'] as $wrapItem) {
        if ($wrapItem['tag']) {
            echo $this->html5Tag()->open($wrapItem['tag'], $wrapItem['attribs']);
        }
    }
    
    if (isset($item['content'])) {
        $tagC = \Ufw\array_get_and_unset($item['content'], 'tag');
        echo $this->html5Tag()->full($tagC ?? 'a', $item['content'], $item['caption'] ?? '');
    } else {
        echo @$item['caption'];
    }
    
    if (isset($item['items'])) {
        echo $this->partial('menu-generic.php', [
            'items' => $item['items'],
            'currentUrl' => @$currentUrl
        ]);
    }
    
    foreach ($item['wrapper'] as $wrapItem) {
        if ($wrapItem['tag']) {
            echo $this->html5Tag()->close($wrapItem['tag']);
        }
    }
}

foreach ($wrapper as $wrapItem) {
    if ($wrapItem['tag']) {
        echo $this->html5Tag()->close($wrapItem['tag']);
    }
}

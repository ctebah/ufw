<?php

/**
 * @var array<int|string, mixed> $items
 * @var string $currentUrl
 * @var \Ufw\Helper\Partial $this
 */
$wrapper = \Ufw\array_get_and_unset($items, 'wrapper');
if (!is_array($wrapper)) {
    $wrapper = array();
}

$menuCaptionToString = function ($caption) {
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

foreach ($wrapper as $wrapItem) {
    if (is_array($wrapItem) && !empty($wrapItem['tag'])) {
        $wrapTag = $wrapItem['tag'];
        if (is_string($wrapTag)) {
            echo $this->html5Tag()->open($wrapTag, \Ufw\filter_string_keyed_array(isset($wrapItem['attribs']) && is_array($wrapItem['attribs']) ? $wrapItem['attribs'] : array()));
        }
    }
}

foreach ($items as $key => $item) {
    if (!is_numeric($key) || !is_array($item)) {
        continue;
    }

    $itemWrappers = isset($item['wrapper']) && is_array($item['wrapper']) ? $item['wrapper'] : array();
    foreach ($itemWrappers as $wrapItem) {
        if (is_array($wrapItem) && !empty($wrapItem['tag'])) {
            $wrapTag = $wrapItem['tag'];
        if (is_string($wrapTag)) {
            echo $this->html5Tag()->open($wrapTag, \Ufw\filter_string_keyed_array(isset($wrapItem['attribs']) && is_array($wrapItem['attribs']) ? $wrapItem['attribs'] : array()));
        }
        }
    }

    if (isset($item['content']) && is_array($item['content'])) {
        $tagC = \Ufw\array_get_and_unset($item['content'], 'tag');
        $contentAttribs = array();
        foreach ($item['content'] as $attrKey => $attrVal) {
            if (is_string($attrKey)) {
                $contentAttribs[$attrKey] = $attrVal;
            }
        }
        $contentTag = is_string($tagC) ? $tagC : 'a';
        $caption = isset($item['caption']) ? $menuCaptionToString($item['caption']) : '';
        echo $this->html5Tag()->full($contentTag, $contentAttribs, $caption);
    } elseif (isset($item['caption'])) {
        echo $menuCaptionToString($item['caption']);
    }

    if (isset($item['items']) && is_array($item['items'])) {
        echo $this->partial('menu-generic.php', array(
            'items' => $item['items'],
            'currentUrl' => $currentUrl,
        ));
    }

    foreach ($itemWrappers as $wrapItem) {
        if (is_array($wrapItem) && !empty($wrapItem['tag'])) {
            echo $this->html5Tag()->close();
        }
    }
}

foreach ($wrapper as $wrapItem) {
    if (is_array($wrapItem) && !empty($wrapItem['tag'])) {
        echo $this->html5Tag()->close();
    }
}

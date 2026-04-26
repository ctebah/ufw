<?php
/**
 * Attach form element metadata for client scripts (no jQuery).
 *
 * Read back with: {@code window.__ufwElementMeta && window.__ufwElementMeta.get(document.getElementById('…'))}
 * ({@code __ufwElementMeta} is created on first metadata attach and reused).
 *
 * @var \Ufw\Form\Element\Base $element
 * @var \Ufw\Helper\Render $this
 */
$idJson = json_encode((string) $element->getId(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$metaJson = json_encode($element->getMetadata(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
(function () {
    var el = document.getElementById(<?php echo $idJson; ?>);
    if (!el) {
        return;
    }
    var w = window.__ufwElementMeta || (window.__ufwElementMeta = new WeakMap());
    w.set(el, <?php echo $metaJson; ?>);
})();

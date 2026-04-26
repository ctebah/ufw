/**
 * UFW form element metadata (see window.__ufwElementMeta WeakMap from form/_metadata.php).
 *
 * @param {Element|string|null|undefined} el - DOM node, or element id
 * @returns {object|undefined} metadata object, or undefined if missing
 */
(function (g) {
    function ufwMeta(el) {
        if (el == null) {
            return undefined;
        }
        var node = typeof el === 'string' ? document.getElementById(el) : el;
        if (!node || !g.__ufwElementMeta) {
            return undefined;
        }
        return g.__ufwElementMeta.get(node);
    }
    g.ufwMeta = ufwMeta;
})(typeof globalThis !== 'undefined' ? globalThis : window);

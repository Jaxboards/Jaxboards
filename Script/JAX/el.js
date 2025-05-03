export function getComputedStyle(a, b) {
    if (!a) return false;
    if (a.currentStyle) return a.currentStyle;
    if (window.getComputedStyle) return window.getComputedStyle(a, b);
    return false;
}

/**
 * Returns an element's position on the page
 * @param {HTMLElement} el
 */
export function getCoordinates(el) {
    const { x: elX, y: elY, height, width } = el.getBoundingClientRect();

    const x = elX + window.scrollX;
    const y = elY + window.scrollY;

    return {
        x,
        y,
        yh: y + height,
        xw: x + width,
        w: width,
        h: height,
    };
}

export function isChildOf(a, b) {
    return b.contains(a);
}

export function insertBefore(a, b) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode.insertBefore(a, b);
}

export function insertAfter(a, b) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode.insertBefore(a, b.nextSibling);
}

export function replace(a, b) {
    insertBefore(b, a);
    if (a.parentNode) a.parentNode.removeChild(a);
}

export function getHighestZIndex() {
    const allElements = Array.from(document.getElementsByTagName('*'));
    const max = allElements.reduce((maxZ, element) => {
        if (element.style.zIndex && Number(element.style.zIndex) > maxZ) {
            return Number(element.style.zIndex);
        }
        return maxZ;
    }, 0);
    return max + 1;
}

export default {
    getComputedStyle,
    getCoordinates,
    isChildOf,
    insertBefore,
    insertAfter,
    replace,
    getHighestZIndex,
};

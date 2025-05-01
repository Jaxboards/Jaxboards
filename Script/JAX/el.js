export function getComputedStyle(a, b) {
    if (!a) return false;
    if (a.currentStyle) return a.currentStyle;
    if (window.getComputedStyle) return window.getComputedStyle(a, b);
    return false;
}

export function getCoordinates(a) {
    let x = 0;
    let y = 0;
    const h = parseInt(a.offsetHeight, 10) || 0;
    const w = parseInt(a.offsetWidth, 10) || 0;
    let element = a;
    do {
        x += parseInt(element.offsetLeft, 10) || 0;
        y += parseInt(element.offsetTop, 10) || 0;
        element = element.offsetParent;
    } while (element);
    return {
        x,
        y,
        yh: y + h,
        xw: x + w,
        w,
        h,
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

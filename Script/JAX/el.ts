export function getComputedStyle(element: HTMLElement) {
    if (!element) return null;
    if (window.getComputedStyle) return window.getComputedStyle(element);
    return null;
}

/**
 * Returns an element's position on the page
 * @param {HTMLElement} el
 */
export function getCoordinates(el: HTMLElement) {
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

export function isChildOf(a: HTMLElement, b: HTMLElement) {
    return b.contains(a);
}

export function insertBefore(a: HTMLElement, b: HTMLElement) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode?.insertBefore(a, b);
}

export function insertAfter(a: HTMLElement, b: HTMLElement) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode?.insertBefore(a, b.nextSibling);
}

export function replace(a: HTMLElement, b: HTMLElement) {
    insertBefore(b, a);
    if (a.parentNode) a.parentNode.removeChild(a);
}

export function getHighestZIndex() {
    const allElements = Array.from(document.querySelectorAll<HTMLElement>('*'));
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

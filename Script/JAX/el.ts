export function getComputedStyle(element: HTMLElement) {
    return globalThis.getComputedStyle(element);
}

/**
 * Returns an element's position on the page
 * @param {HTMLElement} el
 */
export function getCoordinates(el: HTMLElement) {
    const { x: elX, y: elY, height, width } = el.getBoundingClientRect();

    const x = elX + globalThis.scrollX;
    const y = elY + globalThis.scrollY;

    return {
        x,
        y,
        yh: y + height,
        xw: x + width,
        w: width,
        h: height,
    };
}

export function isChildOf(
    targetElement: HTMLElement,
    parentElement: HTMLElement,
) {
    return parentElement.contains(targetElement);
}

export function insertBefore(targetElement: Element, insertElement: Element) {
    insertElement.before(targetElement);
}

export function insertAfter(targetElement: Element, insertElement: Element) {
    insertElement.after(targetElement);
}

export function replace(targetElement: Element, replaceElement: Element) {
    targetElement.replaceWith(replaceElement);
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

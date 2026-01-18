/**
 * Selects/highlights all contents in an element
 * @param  {Element} element
 * @return {Void}
 */
export function selectAll(element: HTMLElement) {
    if (!globalThis.getSelection) {
        return;
    }

    const range = document.createRange();
    range.selectNode(element);

    const selection = globalThis.getSelection();
    if (!selection) return;

    if (selection.rangeCount) selection.removeAllRanges();
    selection.addRange(range);
}

/**
 * If there's any highlighted text in element, replace it with content
 */
export function replaceSelection(
    element: HTMLInputElement | HTMLTextAreaElement,
    content: string,
) {
    const scroll = element.scrollTop;
    const start = element.selectionStart ?? 0;
    const end = element.selectionEnd ?? 0;
    element.value =
        element.value.substring(0, start) + content + element.value.slice(end);
    element.selectionStart = start + content.length;
    element.selectionEnd = start + content.length;
    element.focus();
    element.scrollTop = scroll;
}

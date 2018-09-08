/**
 * Selects/highlights all contents in an element
 * @param  {Element} element
 * @return {Void}
 */
export function selectAll(element) {
  if (document.selection) {
    const range = document.body.createTextRange();
    range.moveToElementText(element);
    range.select();
  } else if (window.getSelection) {
    const range = document.createRange();
    range.selectNode(element);
    const selection = window.getSelection();
    if (selection.rangeCount) selection.removeAllRanges();
    selection.addRange(range);
  }
}

/**
 * If there's any highlighted text in element, replace it with content
 * @param {Element]} element
 * @param {String} content
 */
export function replaceSelection(element, content) {
  const scroll = element.scrollTop;
  if (document.selection) {
    element.focus();
    document.selection.createRange().text = content;
  } else {
    const s = element.selectionStart;
    const e = element.selectionEnd;
    element.value = element.value.substring(0, s) + content + element.value.substr(e);
    element.selectionStart = s + content.length;
    element.selectionEnd = s + content.length;
  }
  element.focus();
  element.scrollTop = scroll;
}

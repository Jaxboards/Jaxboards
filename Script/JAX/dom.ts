export function toDOM<T extends HTMLElement>(html: string) {
  const div = document.createElement("div");
  div.innerHTML = html;
  return div.firstElementChild as T;
}

export function getCellCoordinates(
  cell?: HTMLTableCellElement | null,
): [number, number] {
  const row = cell?.closest("tr");

  return [row?.rowIndex ?? 0, cell?.cellIndex ?? 0];
}

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

export function getHighestZIndex() {
  const allElements = Array.from(document.querySelectorAll<HTMLElement>("*"));
  const max = allElements.reduce((maxZ, element) => {
    if (element.style.zIndex && Number(element.style.zIndex) > maxZ) {
      return Number(element.style.zIndex);
    }
    return maxZ;
  }, 0);
  return max + 1;
}

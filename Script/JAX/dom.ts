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

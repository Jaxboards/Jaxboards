export function toDOM<T extends HTMLElement>(html: string) {
  const div = document.createElement("div");
  div.innerHTML = html;
  return div.firstElementChild as T;
}

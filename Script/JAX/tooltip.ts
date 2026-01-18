import { emojiTime } from "../components/idle-clock";
import { date } from "./date";
import { getCoordinates, getHighestZIndex } from "./el";

export default function toolTip(el: HTMLElement) {
  let tooltip = document.querySelector<HTMLTableElement>("#tooltip_thingy");
  const pos = getCoordinates(el);
  let title = el.getAttribute("title");
  if (el.dataset.lastOnline) {
    const timestamp = Number.parseInt(el.dataset.lastOnline ?? "", 10);
    title = `Last Online: ${date(timestamp)} ${emojiTime(timestamp)}`;
  }
  if (!title) return;
  // Prevent the browser from showing its own title
  el.title = "";
  if (!tooltip) {
    tooltip = document.createElement("table");
    const t = tooltip.insertRow(0);
    const c = tooltip.insertRow(1);
    const b = tooltip.insertRow(2);
    let a;

    tooltip.id = "tooltip_thingy";
    tooltip.className = "tooltip";
    t.className = "top";
    c.className = "content";
    b.className = "bottom";
    a = t.insertCell(0);
    a.className = "left";
    a.colSpan = 2;
    a = t.insertCell(1);
    a.className = "right";
    a = c.insertCell(0);
    a.className = "left";
    a = c.insertCell(1);
    a.innerHTML = "default text";
    a = c.insertCell(2);
    a.className = "right";
    a = b.insertCell(0);
    a.className = "left";
    a.colSpan = 2;
    a = b.insertCell(1);
    a.className = "right";
    document.querySelector("#page")?.appendChild(tooltip);
  }

  tooltip.rows[1].cells[1].innerText = title;
  tooltip.style.display = "";
  tooltip.style.top = `${pos.y - tooltip.clientHeight}px`;
  tooltip.style.left = `${pos.x}px`;
  tooltip.style.zIndex = `${getHighestZIndex()}`;
  el.addEventListener("mouseout", () => {
    el.title = title;
    tooltip.style.display = "none";
  });
}

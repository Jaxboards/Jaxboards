import { emojiTime } from "../components/idle-clock";
import { date } from "./date";
import { getCoordinates, getHighestZIndex, toDOM } from "./dom";

export default function toolTip(el: HTMLElement) {
  const pos = getCoordinates(el);

  let title = el.getAttribute("title");

  if (el.dataset.lastOnline) {
    const timestamp = Number.parseInt(el.dataset.lastOnline ?? "", 10);
    title = `Last Online: ${date(timestamp)} ${emojiTime(timestamp)}`;
  }

  if (!title) return;

  let tooltip = document.querySelector<HTMLTableElement>("#tooltip");
  if (!tooltip) {
    tooltip = toDOM<HTMLTableElement>(
      `<div id="tooltip" class="tooltip">Default text</div>`,
    );

    document.querySelector("#page")?.appendChild(tooltip);
  }

  // Prevent the browser from showing its own title but put it back when done
  el.title = "";
  el.addEventListener("mouseout", () => {
    el.title = title;
    tooltip.style.display = "none";
  });

  tooltip.innerText = title;

  // must set display first for height to be calculable
  tooltip.style.display = "";

  Object.assign(tooltip.style, {
    top: `${pos.y - tooltip.offsetHeight}px`,
    left: `${pos.x}px`,
    zIndex: `${getHighestZIndex()}`,
  });
}

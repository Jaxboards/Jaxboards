import { emojiTime } from "../components/idle-clock";
import { date } from "./date";
import { toDOM } from "./dom";
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

  if (!tooltip) {
    tooltip = toDOM<HTMLTableElement>(`
      <table id="tooltip_thingy" class="tooltip">
        <tr class="top">
          <td class="left" colspan="2"></td>
          <td class="right"></td>
        </tr>
        <tr class="content">
          <td class="left"></td>
          <td>default text</td>
          <td class="right"></td>
        </tr>
        <tr class="bottom">
          <td class="left" colspan="2"></td>
          <td class="right"></td>
        </tr>
      </table>
    `);

    document.body.appendChild(tooltip);
  }

  // Prevent the browser from showing its own title but put it back when done
  el.title = "";
  el.addEventListener("mouseout", () => {
    el.title = title;
    tooltip.style.display = "none";
  });

  tooltip.rows[1].cells[1].innerText = title;

  // must set display first for clientHeight to be calculable
  tooltip.style.display = "";
  Object.assign(tooltip.style, {
    top: `${pos.y - tooltip.clientHeight}px`,
    left: `${pos.x}px`,
    zIndex: `${getHighestZIndex()}`,
  });
}

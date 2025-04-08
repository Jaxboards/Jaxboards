import { getCoordinates, getHighestZIndex } from './el';

function stripHTML(html) {
  if (typeof html !== 'string') {
    // not a string, no HTML to strip
    return html;
  }
  return html.replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export default function toolTip(el) {
  let tooltip = document.getElementById('tooltip_thingy');
  const pos = getCoordinates(el);
  let title = el.getAttribute('title');
  if (!title) return;
  title = stripHTML(title);
  // Prevent the browser from showing its own title
  el.title = '';
  if (!tooltip) {
    tooltip = document.createElement('table');
    const t = tooltip.insertRow(0);
    const c = tooltip.insertRow(1);
    const b = tooltip.insertRow(2);
    let a;

    tooltip.id = 'tooltip_thingy';
    tooltip.className = 'tooltip';
    t.className = 'top';
    c.className = 'content';
    b.className = 'bottom';
    a = t.insertCell(0);
    a.className = 'left';
    a.colSpan = 2;
    a = t.insertCell(1);
    a.className = 'right';
    a = c.insertCell(0);
    a.className = 'left';
    a = c.insertCell(1);
    a.innerHTML = 'default text';
    a = c.insertCell(2);
    a.className = 'right';
    a = b.insertCell(0);
    a.className = 'left';
    a.colSpan = 2;
    a = b.insertCell(1);
    a.className = 'right';
    document.querySelector('#page').appendChild(tooltip);
  }

  tooltip.rows[1].cells[1].innerHTML = title;
  tooltip.style.display = '';
  tooltip.style.top = `${pos.y - tooltip.clientHeight}px`;
  tooltip.style.left = `${pos.x}px`;
  tooltip.style.zIndex = getHighestZIndex();
  el.onmouseout = () => {
    el.title = title;
    document.querySelector('#tooltip_thingy').style.display = 'none';
  };
}

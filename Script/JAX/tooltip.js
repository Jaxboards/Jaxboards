import { getCoordinates, getHighestZIndex } from './el';

function stripHTML(html) {
  return html.valueOf().replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export default function tooltip(el) {
  let toolTip = document.getElementById('tooltip_thingy');
  const pos = getCoordinates(el);
  const title = stripHTML(el.title);
  // Prevent the browser from showing its own title
  el.title = '';
  if (!title) return;
  if (!toolTip) {
    toolTip = document.createElement('table');
    const t = toolTip.insertRow(0);
    const c = toolTip.insertRow(1);
    const b = toolTip.insertRow(2);
    let a;

    toolTip.id = 'tooltip_thingy';
    toolTip.className = 'toolTip';
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
    document.querySelector('#page').appendChild(toolTip);
  }

  toolTip.rows[1].cells[1].innerHTML = title;
  toolTip.style.display = '';
  toolTip.style.top = `${pos.y - toolTip.clientHeight}px`;
  toolTip.style.left = `${pos.x}px`;
  toolTip.style.zIndex = getHighestZIndex();
  el.onmouseout = () => {
    el.title = title;
    document.querySelector('#tooltip_thingy').style.display = 'none';
  };
}

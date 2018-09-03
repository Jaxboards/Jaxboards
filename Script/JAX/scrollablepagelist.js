import Browser from './browser';
import Event from './event';

// scrolling page list functionality
function scrollpagelist(event) {
  const e = Event(event).cancel();
  const wheelDelta = e.detail || e.wheelDelta;
  let delta = Math.abs(wheelDelta) / wheelDelta;
  if (Browser.chrome) {
    delta *= -1;
  }
  const p = this.querySelectorAll('a');
  const startPage = parseInt(p[1].innerHTML, 10);
  const lastPage = parseInt(p[p.length - 1].innerHTML, 10);
  const between = p.length - 2;
  if (Browser.ie) {
    delta *= -1;
  }
  if ((delta > 0 && startPage + between < lastPage) || (delta < 0 && startPage > 2)) {
    for (let x = 0; x < between; x += 1) {
      p[x + 1].href = p[x + 1].href.replace(/\d+$/, x + startPage + delta);
      p[x + 1].innerHTML = startPage + x + delta;
    }
  }
}
export default function (pl) {
  if (pl.addEventListener) {
    pl.addEventListener('DOMMouseScroll', scrollpagelist, false);
  }
  pl.onmousewheel = scrollpagelist;
}

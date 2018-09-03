import Browser from './browser';
import Event from './event';

// scrolling page list functionality
function scrollpagelist(e) {
  e = Event(e).cancel();
  var d = e.detail || e.wheelDelta;
  d = Math.abs(d) / d;
  if (Browser.chrome) d *= -1;
  var x;
  var p = this.querySelectorAll("a");
  var s = parseInt(p[1].innerHTML);
  var e = parseInt(p[p.length - 1].innerHTML);
  var b = p.length - 2;
  if (Browser.ie) d *= -1;
  if ((d > 0 && s + b < e) || (d < 0 && s > 2)) {
    for (x = 0; x < b; x++) {
      p[x + 1].href = p[x + 1].href.replace(/\d+$/, x + s + d);
      p[x + 1].innerHTML = s + x + d;
    }
  }
}
export default function(pl) {
  if (pl.addEventListener) {
    pl.addEventListener("DOMMouseScroll", scrollpagelist, false);
  }
  pl.onmousewheel = scrollpagelist;
};
import Event from './event';
import {
  getCoordinates,
  getComputedStyle,
  insertBefore
} from './el';

export const imageResizer = function(imgs) {
  var img;
  var c;
  var mw;
  var mh;
  var p;
  var p2;
  var ih;
  var iw;
  var x;
  var s;
  if (!imgs) return;
  if (!imgs.length) imgs = [imgs];
  for (var x = 0; x < imgs.length; x++) {
    p = p2 = 1;
    (img = imgs[x]),
      (nw = iw = parseInt(img.naturalWidth)),
      (nh = ih = parseInt(img.naturalHeight));
    if (img.madeResized) continue;
    s = getComputedStyle(img);
    mw = parseInt(s.width) || parseInt(s.maxWidth);
    mh = parseInt(s.height) || parseInt(s.maxHeight);
    if (mw && iw > mw) p = mw / iw;
    if (mh && ih > mh) p2 = mh / ih;
    p = p && p2 ? Math.min(p, p2) : p2 ? p2 : p;
    if (p < 1) {
      iw *= p;
      ih *= p;
      new this.makeResizer(iw, nw, ih, nh, img);
    }
  }
};

export const makeResizer = function(iw, nw, ih, nh, img) {
  img.style.maxWidth = img.style.maxHeight = "999999px";
  img.madeResized = true;
  c = document.createElement("a");
  c.target = "newwin";
  c.href = img.src;
  c.style.display = "block";
  c.style.overflow = "hidden";
  c.style.width = iw + "px";
  c.style.height = ih + "px";
  c.nw = nw;
  c.nh = nh;
  c.onmousemove = function(e) {
    var o = getCoordinates(this);
    e = Event(e);
    this.scrollLeft = ((e.pageX - o.x) / o.w) * (this.nw - o.w);
    this.scrollTop = ((e.pageY - o.y) / o.h) * (this.nh - o.h);
  };
  c.onmouseover = function() {
    img.style.width = this.nw + "px";
    img.style.height = this.nh + "px";
  };
  c.onmouseout = function() {
    if (this.scrollLeft) this.scrollLeft = this.scrollTop = 0;
    img.style.width = iw + "px";
    img.style.height = ih + "px";
  };
  c.onmouseout();
  insertBefore(c, img);
  c.appendChild(img);
};
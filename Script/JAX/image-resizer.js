import Event from './event';
import {
  getCoordinates,
  getComputedStyle,
  insertBefore,
} from './el';

const maxDimension = '999999px';

export function makeResizer(iw, nw, ih, nh, img) {
  img.style.maxWidth = maxDimension;
  img.style.maxHeight = maxDimension;
  img.madeResized = true;
  const link = document.createElement('a');
  link.target = 'newwin';
  link.href = img.src;
  link.style.display = 'block';
  link.style.overflow = 'hidden';
  link.style.width = `${iw}px`;
  link.style.height = `${ih}px`;
  link.nw = nw;
  link.nh = nh;
  link.onmousemove = (event) => {
    const o = getCoordinates(this);
    const e = Event(event);
    this.scrollLeft = ((e.pageX - o.x) / o.w) * (this.nw - o.w);
    this.scrollTop = ((e.pageY - o.y) / o.h) * (this.nh - o.h);
  };
  link.onmouseover = () => {
    img.style.width = `${this.nw}px`;
    img.style.height = `${this.nh}px`;
  };
  link.onmouseout = () => {
    if (this.scrollLeft) {
      this.scrollLeft = 0;
      this.scrollTop = 0;
    }
    img.style.width = `${iw}px`;
    img.style.height = `${ih}px`;
  };
  link.onmouseout();
  insertBefore(link, img);
  link.appendChild(img);
}


export function imageResizer(imgs) {
  let mw;
  let mh;
  let s;
  if (!imgs || !imgs.length) {
    return;
  }
  Array.from(imgs)
    .filter(img => !img.madeResized)
    .forEach((img) => {
      let p = 1;
      let p2 = 1;
      const { naturalWidth, naturalHeight } = img;
      let iw = naturalWidth;
      let ih = naturalHeight;
      s = getComputedStyle(img);
      mw = parseInt(s.width, 10) || parseInt(s.maxWidth, 10);
      mh = parseInt(s.height, 10) || parseInt(s.maxHeight, 10);
      if (mw && iw > mw) p = mw / iw;
      if (mh && ih > mh) p2 = mh / ih;
      p = p && p2 ? Math.min(p, p2) : p2 || p;
      if (p < 1) {
        iw *= p;
        ih *= p;
        makeResizer(iw, naturalWidth, ih, naturalHeight, img);
      }
    });
}

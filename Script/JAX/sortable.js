import {
  getCoordinates,
  insertBefore,
  insertAfter,
} from './el';

/**
 * Swaps two elements in an array
 * @param  {Array} array
 * @param  {Number} fromIndex
 * @param  {Number} toIndex
 * @return {Array}
 */
function swap(array, fromIndex, toIndex) {
  const cache = array[fromIndex];
  array[fromIndex] = array[toIndex];
  array[toIndex] = cache;
  return array;
}

class Sortable extends Drag {
  constructor(elements, options = {}) {
    super();
    this.options = options;
    this.coords = [];
    this.elems = elements;
    if (options.vertical) {
      this.bounds = [0, -Infinity, 0, Infinity];
    }

    for (let x = 0; x < elements.length; x++) {
      me.apply(elements[x], typeof b.handle === 'function' ? b.handle(a[x]) : null);
    }
  }

  ondrop(element) {
    if (me.change) me.coords = [];
    me.change = 0;
    const s = element.el.style;
    s.top = s.left = 0;
    if (typeof me.onend === 'function') me.onend(element);
  }

  ondrag(a) {
    let x;
    let y;
    const d = me.elems;
    const dl = d.length;
    const pos = 0;
    let c;
    const cel = getCoordinates(a.el);
    let c2;
    let ch = false;
    const ov = me.options.vertical || 0;
    const oh = me.options.horizontal || 0;
    let index;
    if (!me.coords.length) {
      for (x = 0; x < dl; x++) me.coords.push(getCoordinates(d[x]));
    }
    for (x = 0; x < dl; x++) {
      if (a.el == d[x]) {
        index = x;
        break;
      }
      c = me.coords[x];
      if (
        ch === false
        && (ov ? a.my < c.yh && a.dy < 0 : a.mx < c.xw && a.my < c.yh)
      ) {
        insertBefore(a.el, d[x]);
        ch = x;
      }
    }
    if (ch === false) {
      for (x = dl - 1; x >= index; x--) {
        if (a.el == d[x]) continue;
        c = me.coords[x];
        if (ov ? a.my > c.y && a.dy > 0 : a.mx > c.x && a.my > c.y) {
          insertAfter(a.el, d[x]);
          if (d.swap) {
            me.elems = swap(index, x);
          }
          ch = 1;
          break;
        }
      }
    } else if (d.swap) {
      me.elems = swap(index, ch);
    }
    if (ch !== false) {
      me.coords = [];
      me.change = 1;
      c2 = getCoordinates(a.el);
      me.sess.ex -= c2.x - cel.x;
      me.sess.ey -= c2.y - cel.y;
      me.priv.drag(a.e);
    }
    return false;
  }
}

export default Sortable;

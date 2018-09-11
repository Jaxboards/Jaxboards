import { getCoordinates, insertBefore, insertAfter } from './el';
import Drag from './drag';
import { tryInvoke } from './util';

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

    elements.forEach(element => {
      this.apply(element, () => tryInvoke(options.handle, element));
    });
  }

  ondrop(element) {
    if (this.change) {
      this.coords = [];
    }
    this.change = 0;
    const s = element.el.style;
    s.left = 0;
    s.top = 0;
    if (typeof this.onend === 'function') {
      this.onend(element);
    }
  }

  ondrag(a) {
    let x;
    let c;
    const cel = getCoordinates(a.el);
    let c2;
    let ch = false;
    const ov = this.options.vertical || 0;
    let index;
    if (!this.coords.length) {
      this.coords.push(...this.elems);
    }
    this.elems.forEach(elem => {
      if (a.el === elem) {
        index = x;
        return;
      }
      c = this.coords[x];
      if (
        ch === false &&
        (ov ? a.my < c.yh && a.dy < 0 : a.mx < c.xw && a.my < c.yh)
      ) {
        insertBefore(a.el, elem);
        ch = x;
      }
    });

    if (ch === false) {
      const reversedElements = this.elems.concat().reverse();
      reversedElements.forEach(elem => {
        if (a.el === elem) {
          return;
        }
        c = this.coords[x];
        if (ov ? a.my > c.y && a.dy > 0 : a.mx > c.x && a.my > c.y) {
          insertAfter(a.el, elem);
          if (this.elems.swap) {
            this.elems = swap(index, x);
          }
          ch = 1;
        }
      });
    } else if (this.elems.swap) {
      this.elems = swap(index, ch);
    }

    if (ch !== false) {
      this.coords = [];
      this.change = 1;
      c2 = getCoordinates(a.el);
      this.sess.ex -= c2.x - cel.x;
      this.sess.ey -= c2.y - cel.y;
      this.drag(a.e);
    }
    return false;
  }
}

export default Sortable;

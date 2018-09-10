import {
  getCoordinates,
  getComputedStyle,
  getHighestZIndex,
  isChildOf
} from './el';
import Event from './event';
import { assign, tryInvoke } from './util';

class Drag {
  constructor() {
    this.droppables = [];
  }

  start(event, t, handle) {
    const e = new Event(event).cancel().stopBubbling();
    const el = t || event.target;
    const s = getComputedStyle(el);
    const highz = getHighestZIndex();
    if (this.noChild && (e.srcElement || e.target) !== (handle || el)) {
      return;
    }
    if (el.getAttribute('draggable') === 'false') {
      return;
    }
    this.sess = {
      el,
      mx: parseInt(e.pageX, 10),
      my: parseInt(e.pageY, 10),
      ex: parseInt(s.left, 10) || 0,
      ey: parseInt(s.top, 10) || 0,
      info: {},
      bc: getCoordinates(el),
      zIndex: el.style.zIndex
    };
    if (!this.sess.zIndex || Number(this.sess.zIndex) < highz - 1) {
      el.style.zIndex = highz;
    }
    tryInvoke(this.onstart, {
      ...this.sess,
      droptarget: this.testDrops(this.sess.mx, this.sess.my)
    });
    this.boundEvents = {
      drag: event2 => this.drag(event2),
      drop: event2 => this.drop(event2)
    };
    document.addEventListener('mousemove', this.boundEvents.drag);
    document.addEventListener('mouseup', this.boundEvents.drop);
    this.drag(e);
  }

  drag(event) {
    const e = new Event(event).cancel();
    const s = this.sess.el.style;
    let sess;
    let tmp = false;
    const tx = parseInt(e.pageX, 10);
    const ty = parseInt(e.pageY, 10);
    let mx = tx;
    let my = ty;
    let tmp2;
    let left = this.sess.ex + mx - this.sess.mx;
    let top = this.sess.ey + my - this.sess.my;
    const b = this.bounds;
    if (b) {
      if (left < b[0]) {
        mx = mx - left + b[0];
        [left] = b;
      } else if (left > b[0] + b[2]) left = b[0] + b[2];
      if (top < b[1]) {
        my = my - top + b[1];
        [top] = b;
      } else if (top > b[1] + b[3]) top = b[1] + b[3];
    }
    s.left = `${left}px`;
    s.top = `${top}px`;
    tmp = (sess = this.sess.info).droptarget;
    sess = {
      left,
      top,
      e,
      el: this.sess.el,
      mx,
      my,
      droptarget: this.testDrops(tx, ty),
      dx: mx - (sess.mx || mx),
      dy: my - (sess.my || my),
      sx: this.sess.ex,
      sy: this.sess.ey
    };
    this.sess.info = sess;
    tryInvoke(this.ondrag, sess);
    if (sess.droptarget && tmp !== sess.droptarget) {
      tryInvoke(this.ondragover, sess);
    }
    if (tmp && sess.droptarget !== tmp) {
      tmp2 = sess.droptarget;
      sess.droptarget = tmp;
      tryInvoke(this.ondragout, sess);
      sess.droptarget = tmp2;
    }
  }

  boundingBox(x, y, w, h) {
    this.bounds = [x, y, w, h];
    return this;
  }

  drop() {
    document.removeEventListener('mouseup', this.boundEvents.drop);
    document.removeEventListener('mousemove', this.boundEvents.drag);
    tryInvoke(this.ondrop, this.sess.info);
    if (!this.autoZ) {
      this.sess.el.style.zIndex = this.sess.zIndex;
    }
    return true;
  }

  testDrops(a, b) {
    const { droppables } = this;
    let z;
    let r = false;
    let max = [9999, 9999];
    if (!droppables.length) {
      return r;
    }
    droppables.forEach(droppable => {
      if (droppable === this.sess.el || isChildOf(droppable, this.sess.el)) {
        return;
      }
      z = getCoordinates(droppable);
      if (
        max[0] > z.w &&
        max[1] > z.h &&
        a >= z.x &&
        b >= z.y &&
        a <= z.xw &&
        b <= z.yh
      ) {
        max = [z.w, z.h];
        r = droppable;
      }
    });
    return r;
  }

  drops(a) {
    this.droppables = a;
    return this;
  }

  addDrops(a) {
    if (!this.droppables) {
      return this.drops(a);
    }
    this.droppables = this.droppables.concat(a);
    return this;
  }

  addListener(a) {
    assign(this, a);
    return this;
  }

  apply(el, t) {
    if (Array.isArray(el)) {
      el.forEach(el2 => this.apply(el2));
      return this;
    }

    let pos = getComputedStyle(el, '');
    pos = pos.position;
    if (!pos || pos === 'static') {
      el.style.position = 'relative';
    }
    (t || el).onmousedown = t
      ? e => this.start(e, el, t)
      : e => this.start(e, el);
    return this;
  }

  autoZ() {
    this.autoZ = true;
    return this;
  }

  noChildActivation() {
    this.noChild = true;
    return this;
  }

  reset(el = this.sess.el) {
    el.style.top = 0;
    el.style.left = 0;
    return this;
  }
}

export default Drag;

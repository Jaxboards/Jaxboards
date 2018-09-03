import {
  getCoordinates,
  getComputedStyle,
  getHighestZIndex,
  isChildOf
} from './el';
import Event from './event';
import {
  assign
} from './util';

/**
 * Tries to call a function, if it exists.
 * @param  {Function} method
 * @param  {...any} args
 * @return {any}
 */
function tryInvoke(method, ...args) {
  if (method && typeof method === "function") {
    return method(...args);
  }
};

class Drag {
  constructor() {

  }

  start(event, t, handle) {
    e = new Event(event).cancel().stopBubbling();
    var el = t || this;
    var s = getComputedStyle(el);
    var highz = getHighestZIndex();
    if (this._nochild && (e.srcElement || e.target) != (handle || el)) return;
    if (el.getAttribute("draggable") == "false") return;
    this.sess = {
      el: el,
      mx: parseInt(e.pageX),
      my: parseInt(e.pageY),
      ex: parseInt(s.left) || 0,
      ey: parseInt(s.top) || 0,
      info: {},
      bc: getCoordinates(el),
      zIndex: el.style.zIndex,
    };
    if (!this.sess.zIndex || Number(this.sess.zIndex) < highz - 1) {
      el.style.zIndex = highz;
    }
    tryInvoke(this.onstart, {
      ...this.sess,
      droptarget: this.testDrops(sess.mx, sess.my),
    });
    document.onmousemove = event => this.drag(event);
    document.onmouseup = event => this.drop(event);
    this.drag(e);
  }

  drag(e) {
    e = new Event(e).cancel();
    var s = this.sess.el.style;
    var sess;
    var tmp = false;
    var tx;
    var ty;
    var tmp2;
    var tx;
    var ty;
    var mx = (tx = parseInt(e.pageX));
    var my = (ty = parseInt(e.pageY));
    var left = this.sess.ex + mx - this.sess.mx;
    var top = this.sess.ey + my - this.sess.my;
    var b = this.bounds;
    if (b) {
      if (left < b[0]) {
        mx = mx - left + b[0];
        left = b[0];
      } else if (left > b[0] + b[2]) left = b[0] + b[2];
      if (top < b[1]) {
        my = my - top + b[1];
        top = b[1];
      } else if (top > b[1] + b[3]) top = b[1] + b[3];
    }
    s.left = left + "px";
    s.top = top + "px";
    tmp = (sess = this.sess.info).droptarget;
    this.sess.info = sess = {
      left: left,
      top: top,
      e: e,
      el: this.sess.el,
      mx: mx,
      my: my,
      droptarget: this.testDrops(tx, ty),
      dx: mx - (sess.mx || mx),
      dy: my - (sess.my || my),
      self: me,
      sx: this.sess.ex,
      sy: this.sess.ey
    };
    tryInvoke(this.ondrag, sess);
    if (
      sess["droptarget"] &&
      tmp != sess["droptarget"]
    ) {
      tryInvoke(this.ondragover, sess);
    }
    if (
      tmp &&
      sess["droptarget"] != tmp
    ) {
      tmp2 = sess["droptarget"];
      sess["droptarget"] = tmp;
      tryInvoke(this.ondragout, sess);
      sess["droptarget"] = tmp2;
    }
  }

  boundingBox(x, y, w, h) {
    this.bounds = [x, y, w, h];
    return this;
  }

  drop() {
    document.onmousemove = document.onmouseup = function() {};
    tryInvoke(this.ondrop, this.sess.info);
    if (!me._autoz) this.sess.el.style.zIndex = this.sess.zIndex;
    return true;
  }

  testDrops(a, b) {
    var x;
    var d = me.droppables;
    var z;
    var r = false;
    var max = [9999, 9999];
    if (!d) return r;
    for (x = 0; x < d.length; x++) {
      if (d[x] == this.sess.el || isChildOf(d[x], this.sess.el)) {
        continue;
      }
      z = getCoordinates(d[x]);
      if (
        max[0] > z.w &&
        max[1] > z.h &&
        a >= z.x &&
        b >= z.y &&
        a <= z.xw &&
        b <= z.yh
      ) {
        max = [z.w, z.h];
        r = d[x];
      }
    }
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
    var x;
    if (el[0]) {
      for (x = 0; x < el.length; x++) me.apply(el[x]);
      return me;
    }
    var pos = getComputedStyle(el, "");
    pos = pos.position;
    if (!pos || pos == "static") el.style.position = "relative";
    (t || el).onmousedown = t
      ? function(e) {
          me.start(e, el, this);
        }
      : me.start;
    return this;
  }

  autoZ() {
    this._autoz = true;
    return this;
  }

  noChildActivation() {
    this._nochild = true;
    return this;
  }

  reset(el, zero) {
    if (!el) el = this.sess.el;
    if (zero) {
      el.style.top = el.style.left = 0;
    } else {
      el.style.top = this.sess.ey + "px";
      el.style.left = this.sess.ex + "px";
      el.style.zIndex = this.sess.zIndex;
    }
    return me;
  }
}

export default Drag;
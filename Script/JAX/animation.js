import { getComputedStyle } from './el';
import Color from './color';

class Animation {
  constructor(el, steps, delay, loop) {
    var tmp;
    var x;
    var y;
    this.el = el;
    this.steps = steps || 30;
    this.delay = delay || 20;
    this.stepCount = this.curLineup = 0;
    this.loop = loop || 0;
    this.lineup = [[]];
  }

  play() {
    this.interval = setInterval(function() {
      this.step();
    }, this.delay);
    return this;
  }

  morph(from, percent, to) {
    var x;
    var r;
    if (Array.isArray(from) && from.length == to.length) {
      r = [];
      for (x = 0; x < from.length; x++) {
        r[x] = Math.round(this.morph(from[x], percent, to[x]));
      }
    } else {
      r = (to - from) * percent + from;
    }
    return r;
  };

  step() {
    var curL = this.lineup[this.curLineup];
    var tmp;
    var sc = this.stepCount++;
    var tmp2;
    var x;
    if (typeof curL[0] == "function") {
      curL[0](this.el);
      sc = this.steps;
    } else {
      for (x = 0; x < curL.length; x++) {
        tmp = curL[x];
        tmp2 = this.morph(tmp[1], sc / this.steps, tmp[2]);
        if (tmp[0].match(/color/i)) {
          tmp2 = "#" + (new Color(tmp2)).toHex();
        } else if (tmp[0] != "opacity") tmp2 = Math.round(tmp2);
        this.el.style[tmp[0]] = tmp[3] + tmp2 + tmp[4];
      }
    }
    if (sc == this.steps) {
      if (this.lineup.length - 1 > this.curLineup) {
        this.stepCount = 0;
        this.curLineup++;
      } else if (this.loop == 1) {
        this.stepCount = this.curLineup = 0;
      } else clearInterval(this.interval);
    }
  }

  add(what, from, to) {
    var t = ["", "", ""];
    if (what.match(/color/i)) {
      from = (new Color(from)).toRGB();
      t[1] = (new Color(to)).toRGB();
    } else {
      (t = to.match(/(\D*)(\-?\d+)(\D*)/)).shift();
      from = parseFloat(from.match(/\-?\d+/));
    }
    this.lineup[this.lineup.length - 1].push([what, from, t[1], t[0], t[2]]);
    return this;
  }

  dehighlight() {
    this.el.style.backgroundColor = "";
    var bg = getComputedStyle(this.el).backgroundColor.toString();
    var bg2;
    this.el.classList.add("highlight");
    bg2 = getComputedStyle(this.el).backgroundColor.toString();
    if (bg2 == bg) bg2 = "FF0";
    this.el.classList.add("highlight");
    return this.add("backgroundColor", bg2, bg).then(function() {
      this.el.style.backgroundColor = bg;
    });
  }

  then(what, from, to, steps) {
    this.lineup.push([]);
    if (steps) this.steps = steps;
    if (typeof what == "function") {
      this.lineup[this.lineup.length - 1].push(what);
    } else {
      this.add(what, from, to);
    }
    return this;
  };
}

export default Animation;
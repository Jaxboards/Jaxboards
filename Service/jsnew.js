Array.prototype.swap = function(a, b) {
  var x = this[a];
  this[a] = this[b];
  this[b] = x;
  return this;
};
Array.prototype.inArray = function(a) {
  for (var x = 0; x < this.length; x++) if (this[x] == a) return x;
  return false;
};
String.prototype.striphtml = function() {
  return this.valueOf()
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
};

Math.rand = function(a, b) {
  if (!b) {
    b = a;
    a = 0;
  }
  return Math.round(Math.random() * b + a);
};

function $() {
  var x,
    l,
    r = [],
    a = arguments;
  for (x = 0, l = a.length; x < l; x++) {
    if (typeof a[x] == "string") {
      r.push(document.getElementById(arguments[x]));
    } else if (typeof a[x] == "function") {
      if (window.loaded) {
        window.onload = function() {};
        window.onloads = null;
        a[x]();
      } else {
        addEvent(window, "onload", a[x]);
      }
    }
  }
  return r.length > 1 ? r : r[0];
}

function addEvent(obj, att, set) {
  var atts = att + "s";
  if (!obj[atts]) {
    obj[atts] = Array();
    if (obj[att]) obj[atts].push(obj[att]);
    obj[att] = function(e) {
      for (var x = 0; x < obj[atts].length; x++) obj[atts][x].call(this, e);
    };
  }
  obj[atts].push(set);
  return obj[atts].length - 1;
}

function $$(s, base) {
  var x,
    y,
    s = s.split(" "),
    l = s.length,
    curel = [],
    base = base || document;
  for (var y = 0; y < l; y++) {
    s[y].match(/([\#.]?)(.+)/);
    switch (RegExp.$1) {
      case "#":
        curel = [$(RegExp.$2)];
        break;
      case ".":
        curel2 = curel;
        curel = [];
        if (curel2.length)
          for (x = 0; x < curel2.length; x++)
            curel = curel.concat(
              JAX.el.getElementsByClassName(curel2[x] || base, RegExp.$2)
            );
        else curel = JAX.el.getElementsByClassName(base, RegExp.$2);
        break;
      default:
        curel2 = curel;
        curel = [];
        if (curel2.length)
          for (x = 0; x < curel2.length; x++)
            curel = curel.concat(curel2[x].getElementsByTagName(RegExp.$2));
        else curel = base.getElementsByTagName(RegExp.$2);
        break;
    }
    if (!curel[0]) break;
  }
  return curel[1] ? curel : curel[0] ? curel[0] : false;
}

/*      JAX is the LIBRARY                */
/* None of this is executed independently */

var JAX = new function() {
  this.browser = {
    chrome: !!navigator.userAgent.match(/chrome/i),
    ie: !!navigator.userAgent.match(/msie/i),
    iphone: !!navigator.userAgent.match(/iphone/i),
    mobile: !!navigator.userAgent.match(/mobile/i),
    n3ds: !!navigator.userAgent.match(/nintendo 3ds/),
    firefox: !!navigator.userAgent.match(/firefox/i),
    safari: !!navigator.userAgent.match(/safari/i)
  };

  var extend = (this.extend = function(a, b) {
    var x;
    for (x in b) a[x] = b[x];
    return a;
  });

  var d = document,
    dE = document.documentElement,
    JAX = this;

  this.date = function(a) {
    var old = new Date(),
      now = new Date(),
      fmt,
      hours,
      mins,
      delta,
      ampm,
      yday = new Date(),
      dstr,
      months = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec"
      ];
    yday.setTime(yday - 1000 * 60 * 60 * 24);
    old.setTime(a * 1000); //setTime uses milliseconds, we'll be using UNIX Times as the argument
    hours = old.getHours();
    mins = old.getMinutes();
    ampm = hours >= 12 ? "pm" : "am";
    dstr = old.getDate() + " " + old.getMonth() + " " + old.getFullYear();
    hours %= 12;
    hours = hours || 12;
    delta = (now.getTime() - old.getTime()) / 1000;
    if (delta < 90) fmt = "a minute ago";
    else if (delta < 3600) fmt = Math.round(delta / 60) + " minutes ago";
    else if (
      now.getDate() + " " + now.getMonth() + " " + now.getFullYear() ==
      dstr
    )
      fmt = "Today @ " + hours + ":" + JAX.prepad(mins, 2) + " " + ampm;
    else if (
      yday.getDate() + " " + yday.getMonth() + " " + yday.getFullYear() ==
      dstr
    )
      fmt = "Yesterday @ " + hours + ":" + JAX.prepad(mins, 2) + " " + ampm;
    else
      fmt =
        months[old.getMonth()] +
        " " +
        JAX.ordsuffix(old.getDate()) +
        ", " +
        old.getFullYear() +
        " @ " +
        hours +
        ":" +
        JAX.prepad(mins, 2) +
        " " +
        ampm;
    return fmt;
  };

  this.smalldate = function(a) {
    var d = new Date(),
      h,
      ampm;
    d.setTime(a * 1000);
    h = d.getHours();
    ampm = h >= 12 ? "pm" : "am";
    h %= 12;
    h = h || 12;
    return (
      h +
      ":" +
      JAX.prepad(d.getMinutes(), 2) +
      ampm +
      ", " +
      (d.getMonth() + 1) +
      "/" +
      JAX.prepad(d.getDate(), 2) +
      "/" +
      d.getFullYear()
    );
  };

  this.ordsuffix = function(a) {
    return (
      a +
      (Math.round(a / 10) == 1 ? "th" : ["", "st", "nd", "rd"][a % 10] || "th")
    );
  };

  this.prepad = function(st, len, chr) {
    st = st.toString();
    if (!chr) chr = "0";
    var l = st.length;
    for (var x = 0; x < len - l; x++) st = chr + st;
    return st;
  };

  this.API = {
    getMostRecentTopics: function(cb) {}
  };

  this.autoComplete = function(a, el, dummy, e) {
    if (e) e = JAX.event(e);
    else e = {};
    el.onkeydown = function(e) {
      e = JAX.event(e);
      if (e.ENTER) {
        e.cancel();
        return false;
      }
    };
    var d = $("autocomplete"),
      coords = JAX.el.getCoordinates(el),
      els,
      sindex,
      x,
      l = 0;
    if (!d) {
      d = document.createElement("div");
      d.id = "autocomplete";
      d.style.position = "absolute";
      d.style.zIndex = JAX.el.getHighestZIndex();
      $("page").appendChild(d);
    } else {
      d.style.display = "";
      els = d.getElementsByTagName("div");
      l = els.length || 0;
      for (x = 0; x < l; x++)
        if (JAX.el.hasClass(els[x], "selected")) {
          sindex = x;
          break;
        }
    }
    d.style.top = coords.yh + "px";
    d.style.left = coords.x + "px";
    d.style.width = coords.w + "px";
    if (e.UP && l && sindex >= 1 && typeof sindex != "undefined") {
      JAX.el.removeClass(els[sindex], "selected");
      JAX.el.addClass(els[sindex - 1], "selected");
    } else if (
      e.DOWN &&
      l &&
      (sindex < l - 1 || typeof sindex == "undefined")
    ) {
      if (typeof sindex == "undefined") {
        JAX.el.addClass(els[0], "selected");
      } else {
        JAX.el.removeClass(els[sindex], "selected");
        JAX.el.addClass(els[sindex + 1], "selected");
      }
    } else if (e.ENTER && l && typeof sindex != undefined) {
      els[sindex].onclick();
    } else {
      var a = new JAX.ajax().load(
        (document.location.toString().match("/acp/") ? "../" : "") +
          "misc/listloader.php?" +
          a,
        function(xml) {
          var x, tmp;
          xml = eval(xml.responseText);
          d.innerHTML = "";
          for (x = 0; x < xml[0].length; x++) {
            tmp = document.createElement("div");
            tmp.innerHTML = xml[1][x];
            tmp.key = xml[0][x];
            tmp.onclick = function() {
              this.parentNode.style.display = "none";
              if (dummy) {
                dummy.value = this.key;
                if (dummy.onchange) dummy.onchange();
              }
              el.value = this.innerHTML;
            };
            d.appendChild(tmp);
          }
          if (!xml[0].length) d.style.display = "none";
        }
      );
    }
  };

  this.select = function(a) {
    var r;
    if (document.selection) {
      r = document.body.createTextRange();
      r.moveToElementText(a);
      r.select();
    } else if (window.getSelection) {
      r = document.createRange();
      r.selectNode(a);
      window.getSelection().addRange(r);
    }
  };

  this.flashTitle = function(a) {
    if (window.hasFocus) return true;
    this.stopTitleFlashing();
    if (document.titleorig == "") document.titleorig = document.title;
    document.title2 = a;
    JAX.flashInterval = setInterval(function() {
      document.title =
        document.title == document.titleorig
          ? document.title2
          : document.titleorig;
    }, 1000);
  };
  this.stopTitleFlashing = function() {
    if (document.titleorig) document.title = document.titleorig;
    document.titleorig = "";
    clearInterval(JAX.flashInterval);
  };

  this.gracefulDegrade = function(a) {
    if (typeof RUN != "undefined") RUN.updateDates();
    var tmp,
      links = a.getElementsByTagName("a"),
      l = links.length,
      x,
      old;

    for (x = 0; x < l; x++) {
      if (links[x].href) {
        if (links[x].getAttribute("href").charAt(0) == "?") {
          if (links[x].onclick) links[x].oldclick = links[x].onclick;
          links[x].onclick = function() {
            if (!this.oldclick || this.oldclick() != false)
              RUN.stream.location(this.getAttribute("href"));
            return false;
          };
        } else if (links[x].getAttribute("href").substr(0, 4) == "http") {
          links[x].target = "_BLANK";
        }
      }
    }
    JAX.convertSwitches($$(".switch", a));

    var bbcodeimgs = $$(".bbcodeimg");
    if (bbcodeimgs)
      JAX.onImagesLoaded(
        bbcodeimgs,
        function() {
          //resizer on large images
          JAX.imageResizer(bbcodeimgs);

          //handle image galleries
          var galleries = $$(".image_gallery");
          if (galleries && !galleries.length) galleries = [galleries];
          for (x = 0; x < galleries.length; x++)
            JAX.makeImageGallery(galleries[x]);
        },
        2000
      );

    if ((tmp = $$(".pages", a))) {
      if (!tmp.length) tmp = [tmp];
      for (x = 0; x < tmp.length; x++) JAX.scrollablepagelist(tmp[x]);
    }

    if ((tmp = $$(".date", a))) {
      if (!tmp.length) tmp = [tmp];
      for (var x = 0; x < tmp.length; x++) {
        if (tmp[x].tagName != "INPUT") continue;
        tmp[x].onclick = function() {
          JAX.datepicker.init(this);
        };
        tmp[x].onkeydown = function() {
          JAX.datepicker.hide();
        };
      }
    }

    if (JAX.event.onUpdate) JAX.event.onUpdate(a);
  };

  this.convertSwitches = function(switches) {
    if (!switches) return;
    if (!Array.isArray(switches)) switches = [switches];
    var x,
      l = switches.length,
      s,
      t;
    for (x = 0; x < l; x++) {
      s = switches[x];
      t = document.createElement("div");
      t.className = s.className.replace("switch", "switch_converted");
      t.s = s;
      s.style.display = "none";
      if (!s.checked) t.style.backgroundPosition = "bottom";
      t.onclick = function() {
        this.s.checked = !this.s.checked;
        this.style.backgroundPosition = this.s.checked ? "top" : "bottom";
        if (this.s.onclick) this.s.onclick();
      };
      JAX.el.insertAfter(t, s);
    }
  };

  this.checkAll = function(checkboxes, value) {
    for (var x = 0; x < checkboxes.length; x++) checkboxes[x].checked = value;
  };

  this.onImagesLoaded = function(imgs, f, timeout) {
    var x,
      dbj = {
        imgs: [],
        imgsloaded: 1,
        called: false,
        force: function() {
          if (!dbj.called) f();
        }
      };
    dbj.callback = function(event) {
      if (dbj.called) return;
      var x = dbj.imgs.inArray(this.src);
      if (x === false) return;
      dbj.imgs.splice(x, 1);
      if (dbj.imgs.length == 0) {
        f();
        dbj.called = true;
      }
    };
    for (x = 0; x < imgs.length; x++) {
      if (dbj.imgs.inArray(imgs[x].src) === false && !imgs[x].loaded) {
        dbj.imgs.push(imgs[x].src);
        addEvent(imgs[x], "onload", dbj.callback);
        imgs[x].src = imgs[x].src;
      }
    }
    if (!imgs.length) {
      f();
      dbj.called = true;
    } else if (timeout) setTimeout(dbj.force, timeout);
  };

  this.handleTabs = function(e, a, f) {
    var e = e || window.event,
      el = e.target || e.srcElement,
      act;
    if (el.tagName.toLowerCase() != "a") return;
    if (f) el = f(el);
    act = JAX.el.getElementsByClassName(a, "active")[0];
    if (act) act.className = "";
    el.className = "active";
    el.blur();
  };

  this.toggle = function(a) {
    if (a.style.display == "none") a.style.display = "";
    else a.style.display = "none";
  };

  this.collapse = function(a) {
    var s = a.style,
      fh = a.getAttribute("fullHeight"),
      b = a.parentNode;
    s.overflow = "hidden";
    if (s.height == "0px") {
      JAX.sfx(a, 5, 10, 0)
        .add("height", "0px", fh)
        .then(function() {
          JAX.el.removeClass(b, "collapsed");
        })
        .play();
    } else {
      if (!fh) {
        fh = (a.clientHeight || a.offsetHeight) + "px";
        a.setAttribute("fullHeight", fh);
      }
      JAX.sfx(a, 5, 10, 0)
        .add("height", fh, "0px")
        .then(function() {
          JAX.el.addClass(b, "collapsed");
        })
        .play();
    }
  };

  this.overlay = function(show) {
    show = parseInt(show);
    var ol = document.getElementById("overlay"),
      s,
      op;
    if (ol) {
      s = ol.style;
      s.zIndex = JAX.el.getHighestZIndex();
      s.top = 0;
      s.height = dE.clientHeight + "px";
      s.width = dE.clientWidth + "px";
      s.display = show ? "" : "none";
    } else {
      if (!show) return;
      ol = document.createElement("div");
      s = ol.style;
      ol.id = "overlay";
      s.height = dE.clientHeight + "0px";
      s.width = dE.clientWidth + "0px";
      dE.appendChild(ol);
    }
  };

  /********************************************************************/

  this.el = {
    apply: function(a) {
      var func;
      for (var x in JAX.el) {
        if (x != "apply")
          a[x] = new Function("b", "return JAX.el." + x + "(this,b)");
      }
      return a;
    },
    getComputedStyle: function(a, b) {
      if (!a) return false;
      if (a.currentStyle) return a.currentStyle;
      else if (window.getComputedStyle) return window.getComputedStyle(a, b);
      return false;
    },
    getCoordinates: function(a) {
      var x = 0,
        y = 0,
        h = parseInt(a.offsetHeight) || 0,
        w = parseInt(a.offsetWidth) || 0;
      do {
        x += parseInt(a.offsetLeft) || 0;
        y += parseInt(a.offsetTop) || 0;
      } while ((a = a.offsetParent));
      return {
        x: x,
        y: y,
        yh: y + h,
        xw: x + w,
        w: w,
        h: h
      };
    },

    isChildOf: function(a, b) {
      while ((a = a.parentNode)) if (a == b) return true;
      return false;
    },

    //insert a before b, insert a after b, replace a with b
    insertBefore: function(a, b) {
      if (a.parentNode) a.parentNode.removeChild(a);
      b.parentNode.insertBefore(a, b);
    },
    insertAfter: function(a, b) {
      if (a.parentNode) a.parentNode.removeChild(a);
      b.parentNode.insertBefore(a, b.nextSibling);
    },
    replace: function(a, b) {
      JAX.el.insertBefore(b, a);
      if (a.parentNode) a.parentNode.removeChild(a);
    },
    getElementsByClassName: function(parent, classn) {
      if (!classn) {
        classn = parent;
        parent = document;
      }
      var els = parent.getElementsByTagName("*"),
        x,
        r = [],
        regex = new RegExp(
          " " + classn + "| " + classn + " |" + classn + " |^" + classn + "$"
        );
      for (x = 0; x < els.length; x++)
        if (els[x].className.match(regex)) r.push(els[x]);
      return !r.length ? [] : r;
    },

    addClass: function(a, c) {
      if (!a.className.match(c)) a.className += " " + c;
    },

    removeClass: function(a, c) {
      a.className = a.className.replace(" " + c, "");
    },

    hasClass: function(a, c) {
      return a.className.match(c);
    },

    getHighestZIndex: function() {
      var a = document.getElementsByTagName("*"),
        l = a.length,
        x,
        max = 0;
      for (x = 0; x < l; x++)
        if (a[x].style.zIndex && Number(a[x].style.zIndex) > max)
          max = Number(a[x].style.zIndex);
      return max + 1;
    }
  };
  /********************************************************************/

  this.SWF = function(url, name, settings) {
    var object,
      embed,
      x,
      s = {
        width: "100%",
        height: "100%",
        quality: "high"
      };
    for (x in settings) s[x] = settings[x];
    object =
      '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="' +
      name +
      '" width="' +
      s.width +
      '" height="' +
      s.height +
      '"><param name="movie" value="' +
      url +
      '"></param>';
    embed =
      '<embed style="display:block" type="application/x-shockwave-flash" pluginspage="https://get.adobe.com/flashplayer/" src="' +
      url +
      '" width="' +
      s.width +
      '" height="' +
      s.height +
      '" name="' +
      name +
      '"';
    for (x in s)
      if (x != "width" && x != "height") {
        object += '<param name="' + x + '" value="' + s[x] + '"></param>';
        embed += " " + x + '="' + s[x] + '"';
      }
    embed += "></embed>";
    object += "</object>";
    var tmp = document.createElement("span");
    tmp.innerHTML = JAX.browser.ie ? object : embed;
    return tmp.getElementsByTagName("*")[0];
  };

  /********************************************************************/

  (this.color = function(a) {
    if (this == JAX) return new JAX.color(a);
    var tmp, x;
    if (a.charAt && a.charAt(0) == "#") a = a.substr(1);
    //RGB
    if (typeof a == "object") this.rgb = a;
    else if (a.match && (tmp = a.match(/^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i))) {
      tmp[1] = parseFloat(tmp[1]);
      tmp[2] = parseFloat(tmp[2]);
      tmp[3] = parseFloat(tmp[3]);
      tmp.shift();
      this.rgb = tmp;
      //HEX
    } else if (a.match && !a.match(/[^\da-fA-F]/)) {
      if (a.length == 3)
        a =
          a.charAt(0) +
          a.charAt(0) +
          a.charAt(1) +
          a.charAt(1) +
          a.charAt(2) +
          a.charAt(2);
      if (a.length != 6) this.rgb = [0, 0, 0];
      else {
        this.rgb = [];
        for (x = 0; x < 3; x++) this.rgb[x] = parseInt(a.substr(x * 2, 2), 16);
      }
    } else this.rgb = [0, 0, 0];

    this.invert = function() {
      this.rgb = [255 - this.rgb[0], 255 - this.rgb[1], 255 - this.rgb[2]];
      return this;
    };
    this.toRGB = function() {
      return this.rgb;
    };
    this.toHex = function() {
      if (!this.rgb) return false;
      var tmp2,
        tmp = "",
        x,
        hex = "0123456789ABCDEF";
      for (x = 0; x < 3; x++) {
        tmp2 = this.rgb[x];
        tmp +=
          hex.charAt(Math.floor(tmp2 / 16)) + hex.charAt(Math.floor(tmp2 % 16));
      }
      return tmp;
    };
    return this;
  }),
    /********************************************************************/

    (this.sfx = function(el, steps, delay, loop) {
      var tmp,
        x,
        y,
        me = this;
      if (JAX == me) me = {};
      me.el = el;
      me.steps = steps || 30;
      me.delay = delay || 20;
      me.stepCount = me.curLineup = 0;
      me.loop = loop || 0;
      me.lineup = [[]];

      me.play = function() {
        me.interval = setInterval(function() {
          me.step();
        }, me.delay);
        return this;
      };
      me.morph = function(from, percent, to) {
        var x, r;
        if (Array.isArray(from) && from.length == to.length) {
          r = [];
          for (x = 0; x < from.length; x++)
            r[x] = Math.round(this.morph(from[x], percent, to[x]));
        } else {
          r = (to - from) * percent + from;
        }
        return r;
      };
      me.step = function() {
        var curL = me.lineup[me.curLineup],
          tmp,
          sc = me.stepCount++,
          tmp2,
          x;
        if (typeof curL[0] == "function") {
          curL[0](me.el);
          sc = me.steps;
        } else {
          for (x = 0; x < curL.length; x++) {
            tmp = curL[x];
            tmp2 = me.morph(tmp[1], sc / me.steps, tmp[2]);
            if (tmp[0].match(/color/i)) {
              tmp2 = "#" + JAX.color(tmp2).toHex();
            } else if (tmp[0] != "opacity") tmp2 = Math.round(tmp2);
            this.el.style[tmp[0]] = tmp[3] + tmp2 + tmp[4];
          }
        }
        if (sc == me.steps) {
          if (me.lineup.length - 1 > me.curLineup) {
            me.stepCount = 0;
            me.curLineup++;
          } else if (me.loop == 1) {
            me.stepCount = me.curLineup = 0;
          } else clearInterval(me.interval);
        }
      };
      me.add = function(what, from, to) {
        var t = ["", "", ""];
        if (what.match(/color/i)) {
          from = JAX.color(from).toRGB();
          t[1] = JAX.color(to).toRGB();
        } else {
          (t = to.match(/(\D*)(\-?\d+)(\D*)/)).shift();
          from = parseFloat(from.match(/\-?\d+/));
        }
        me.lineup[me.lineup.length - 1].push([what, from, t[1], t[0], t[2]]);
        return this;
      };
      me.dehighlight = function() {
        me.el.style.backgroundColor = "";
        var bg = JAX.el.getComputedStyle(me.el).backgroundColor.toString(),
          bg2;
        JAX.el.addClass(me.el, "highlight");
        bg2 = JAX.el.getComputedStyle(me.el).backgroundColor.toString();
        if (bg2 == bg) bg2 = "FF0";
        JAX.el.removeClass(me.el, "highlight");
        return me.add("backgroundColor", bg2, bg).then(function() {
          me.el.style.backgroundColor = bg;
        });
      };
      me.then = function(what, from, to, steps) {
        this.lineup.push([]);
        if (steps) this.steps = steps;
        if (typeof what == "function") {
          this.lineup[me.lineup.length - 1].push(what);
        } else {
          this.add(what, from, to);
        }
        return this;
      };
      return me;
    });

  /********************************************************************/

  this.editor = function(textarea, iframe) {
    if (!iframe.timedout) {
      iframe.timedout = true;
      setTimeout(function() {
        JAX.editor(textarea, iframe);
      }, 100);
      return;
    }
    if (iframe.editor) return;
    var me = {};
    me.iframe = iframe;
    iframe.editor = me;
    iframe.className = "editorframe";
    me.mode =
      JAX.browser.mobile || JAX.browser.n3ds ? 0 : globalsettings.wysiwyg; //1 for html editing mode, 0 for textarea mode
    me.mode = me.mode || 0;
    me.textarea = textarea;
    me.window = iframe.contentWindow;
    me.doc = iframe.contentWindow.document;

    var cs = JAX.el.getComputedStyle(me.textarea);
    var body = me.doc.getElementsByTagName("body")[0];
    if (body && cs) {
      body.style.backgroundColor = cs.backgroundColor;
      body.style.color = cs.color;
      body.style.borderColor = "#FFF";
    }

    me.doc.designMode = "on";

    me.buildEditBar = function() {
      me.editbar.className = "editbar";
      var cmds = [
          "bold",
          "italic",
          "underline",
          "strikethrough",
          "forecolor",
          "backcolor",
          "insertimage",
          "createlink",
          "c_email",
          "justifyleft",
          "justifycenter",
          "justifyright",
          "c_youtube",
          "c_code",
          "c_quote",
          "c_spoiler",
          "insertorderedlist",
          "insertunorderedlist",
          "c_smileys",
          "c_switcheditmode"
        ],
        cmddesc = [
          "Bold",
          "Italic",
          "Underline",
          "Strike-Through",
          "Foreground Color",
          "Background Color",
          "Insert Image",
          "Insert Link",
          "Insert email",
          "Align left",
          "Center",
          "Align right",
          "Insert video from any of your favorite video services!",
          "Insert code",
          "Insert Quote",
          "Insert Spoiler",
          "Create Ordered List",
          "Create Unordered List",
          "Insert Emoticon",
          "Switch editor mode"
        ],
        l = cmds.length,
        a,
        x;
      for (x = 0; x < l; x++) {
        a = document.createElement("a");
        a.className = cmds[x];
        a.title = cmddesc[x];
        a.href = "javascript:void(0)";
        a.unselectable = "on";
        a.onclick = function(event) {
          me.editbarCommand(event, this.className);
        };
        me.editbar.appendChild(a);
      }
    };

    me.editbar = document.createElement("div");
    me.buildEditBar();

    me.editbar.style.width = textarea.clientWidth + 2 + "px";
    iframe.style.width = textarea.clientWidth + "px";
    iframe.style.height = textarea.clientHeight + "px";

    JAX.el.insertBefore(me.editbar, textarea);

    me.editbarCommand = function(e, cmd) {
      e = JAX.event(e).cancel();
      if (cmd == "forecolor" || cmd == "backcolor")
        me.showColors(e.pageX, e.pageY, cmd);
      else if (cmd == "c_smileys") me.showEmotes(e.pageX, e.pageY);
      else if (cmd == "c_switcheditmode") me.switchMode(Math.abs(me.mode - 1));
      else this.cmd(cmd);
    };

    me.showEmotes = function(x, y) {
      var emotewin = me.emoteWindow;
      if (!emotewin) {
        me.createEmoteWindow.x = x;
        me.createEmoteWindow.y = y;
        new JAX.ajax().load("/misc/emotes.php?json", me.createEmoteWindow);
        return;
      }
      if (emotewin.style.display == "none") {
        emotewin.style.display = "";
        emotewin.style.top = y + "px";
        emotewin.style.left = x + "px";
      } else {
        me.hideEmotes();
      }
    };

    me.hideEmotes = function() {
      if (me.emoteWindow) me.emoteWindow.style.display = "none";
    };

    me.createEmoteWindow = function(xml) {
      var rs = eval(xml.responseText),
        x,
        html,
        emotewin = document.createElement("div"),
        r,
        t;
      emotewin.className = "emotewin";
      for (x = 0; x < rs[0].length; x++) {
        r = document.createElement("a");
        r.href = "javascript:void(0)";
        r.emotetext = rs[0][x];
        r.onclick = function() {
          me.cmd("inserthtml", this.emotetext);
          me.hideEmotes();
        };
        r.innerHTML = rs[1][x] + " " + rs[0][x];
        emotewin.appendChild(r);
      }
      emotewin.style.position = "absolute";
      emotewin.style.display = "none";
      me.emoteWindow = emotewin;
      $("page").appendChild(emotewin);
      me.showEmotes(me.createEmoteWindow.x, me.createEmoteWindow.y);
    };

    me.colorHandler = function() {
      me.cmd(me.colorHandler.cmd, this.style.backgroundColor);
      me.hideColors();
    };

    me.showColors = function(posx, posy, cmd) {
      if (me.colorWindow && me.colorWindow.style.display != "none")
        return me.hideColors();
      var colorwin = me.colorWindow,
        colors = [
          "FFFFFF",
          "AAAAAA",
          "000000",
          "FF0000",
          "00FF00",
          "0000FF",
          "FFFF00",
          "00FFFF",
          "FF00FF"
        ],
        l = colors.length,
        sq = Math.ceil(Math.sqrt(l)),
        r,
        c,
        a;
      if (!colorwin) {
        colorwin = document.createElement("table");
        colorwin.style.borderCollapse = "collapse";
        colorwin.style.position = "absolute";
        for (y = 0; y < sq; y++) {
          r = colorwin.insertRow(y);
          for (x = 0; x < sq; x++) {
            c = r.insertCell(x);
            if (!colors[x + y * sq]) continue;
            c.style.border = "1px solid #000";
            c.style.padding = 0;
            a = document.createElement("a");
            a.href = "javascript:void(0)";
            a.onclick = me.colorHandler;
            c.appendChild(a);
            c = a.style;
            c.display = "block";
            c.backgroundColor = "#" + colors[x + y * sq];
            c.height = c.width = "20px";
            c.margin = 0;
          }
        }
        me.colorWindow = colorwin;
        $("page").appendChild(colorwin);
      } else {
        colorwin.style.display = "";
      }
      colorwin.style.top = posy + "px";
      colorwin.style.left = posx + "px";
      me.colorHandler.cmd = cmd;
    };

    me.hideColors = function() {
      if (me.colorWindow) me.colorWindow.style.display = "none";
    };

    me.cmd = function(a, b, c) {
      a = a.toLowerCase();
      var rng,
        selection = me.getSelection(),
        bbcode;
      switch (a) {
        case "bold":
          bbcode = "[b]" + selection + "[/b]";
          break;
        case "italic":
          bbcode = "[i]" + selection + "[/i]";
          break;
        case "underline":
          bbcode = "[u]" + selection + "[/u]";
          break;
        case "strikethrough":
          bbcode = "[s]" + selection + "[/s]";
          break;
        case "justifyright":
          bbcode = "[align=right]" + selection + "[/align]";
          break;
        case "justifycenter":
          bbcode = "[align=center]" + selection + "[/align]";
          break;
        case "justifyleft":
          bbcode = "[align=left]" + selection + "[/align]";
          break;
        case "insertimage":
          b = prompt("Image URL:");
          if (!b) return;
          if (!b.match(/^(ht|f)tps?:\/\/[\w\.\-\%&\?=\/]+$/))
            return alert("Please enter a valid URL.");
          bbcode = "[img]" + b + "[/img]";
          break;
        case "insertorderedlist":
          if (!me.mode)
            bbcode =
              "[ol]" + selection.replace(/(.+([\r\n]+|$))/gi, "*$1") + "[/ol]";
          break;
        case "insertunorderedlist":
          if (!me.mode)
            bbcode =
              "[ul]" + selection.replace(/(.+([\r\n]+|$))/gi, "*$1") + "[/ul]";
          break;
        case "createlink":
          b = prompt("Link:");
          if (!b) return;
          if (!b.match(/^(https?|ftp|mailto):/)) b = "https://" + b;
          bbcode = "[url=" + b + "]" + selection + "[/url]";
          break;
        case "c_email":
          b = prompt("Email:");
          if (!b) return;
          a = "createlink";
          b = "mailto:" + b;
          bbcode = "[url=" + b + "]" + selection + "[/url]";
          break;
        case "backcolor":
          if (JAX.browser.firefox || JAX.browser.safari) a = "hilitecolor";
          //a="inserthtml";b='<span style="background:'+b+'">'+selection+'</span>'
          bbcode = "[bgcolor=" + b + "]" + selection + "[/bgcolor]";
          break;
        case "forecolor":
          bbcode = "[color=" + b + "]" + selection + "[/color]";
          break;
        case "c_code":
          a = "inserthtml";
          bbcode = b = "[code]" + selection + "[/code]";
          break;
        case "c_quote":
          a = "inserthtml";
          b = prompt("Who said this?");
          b = bbcode =
            "[quote" + (b ? "=" + b : "") + "]" + selection + "[/quote]";
          break;
        case "c_spoiler":
          a = "inserthtml";
          b = bbcode = "[spoiler]" + selection + "[/spoiler]";
          break;
        case "c_youtube":
          a = "inserthtml";
          b = prompt("Video URL?");
          if (!b) return;
          b = bbcode = "[video]" + b + "[/video]";
          break;
        case "inserthtml":
          bbcode = b;
          break;
      }
      if (me.mode) {
        if (a == "inserthtml" && JAX.browser.ie) {
          rng = me.doc.selection.createRange();
          if (!rng.text.length) me.doc.body.innerHTML += b;
          else {
            rng.pasteHTML(b);
            rng.collapse(false);
            rng.select();
          }
        } else {
          me.doc.execCommand(a, false, b || false);
          if (me.iframe.contentWindow.focus) me.iframe.contentWindow.focus();
        }
      } else JAX.editor.setSelection(me.textarea, bbcode);
    };

    me.getSelection = function() {
      if (me.mode)
        return JAX.browser.ie
          ? me.doc.selection.createRange().text
          : me.window.getSelection();
      else {
        if (JAX.browser.ie) {
          me.textarea.focus();
          return document.selection.createRange().text;
        } else {
          return me.textarea.value.substring(
            me.textarea.selectionStart,
            me.textarea.selectionEnd
          );
        }
      }
    };

    me.getSource = function() {
      return me.doc.body.innerHTML;
    };

    me.setSource = function(a) {
      if (me.doc && me.doc.body) me.doc.body.innerHTML = a;
    };
    me.setSource("<div></div>");

    me.BBtoHTML = function(a) {
      a = a
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/(\s) /g, "$1&nbsp;");
      a = me.replaceAll(a, /\[(b|i|u|s)\]([\w\W]*?)\[\/\1\]/gi, "<$1>$2</$1>");
      a = me.replaceAll(a, /\[img\]([^'"\[]+)\[\/img\]/gi, '<img src="$1">');
      a = me.replaceAll(
        a,
        /\[color=([^\]]+)\](.*?)\[\/color\]/gi,
        '<span style="color:$1">$2</span>'
      );
      a = me.replaceAll(
        a,
        /\[size=([^\]]+)\](.*?)\[\/size\]/gi,
        '<span style="font-size:$1">$2</span>'
      );
      a = me.replaceAll(
        a,
        /\[url=([^\]]+)\](.*?)\[\/url\]/gi,
        '<a href="$1">$2</a>'
      );
      a = me.replaceAll(
        a,
        /\[bgcolor=([^\]]+)\](.*?)\[\/bgcolor\]/gi,
        '<span style="backgroun-color:$1">$2</span>'
      );
      a = me.replaceAll(a, /\[h(\d)\](.*?)\[\/h\1\]/, "<h$1>$2</h$1>");
      a = me.replaceAll(
        a,
        /\[align=(left|right|center)\](.*?)\[\/align\]/,
        '<span style="text-align:$1">$2</span>'
      );
      a = me.replaceAll(a, /\[(ul|ol)\]([\w\W]*?)\[\/\1\]/gi, function(s) {
        var tag = RegExp.$1,
          lis = "",
          list = RegExp.$2.split(/([\r\n]+|^)\*/),
          x;
        for (x = 0; x < list.length; x++)
          if (list[x].match(/\S/)) lis += "<li>" + list[x] + "</li>";
        return "<" + tag + ">" + lis + "</" + tag + ">";
      });
      a = me.replaceAll(a, /\n/g, "<br />");
      return a;
    };

    me.replaceAll = function(a, b, c) {
      var tmp = a;
      do {
        a = tmp;
        tmp = a.replace(b, c);
      } while (a != tmp);
      return tmp;
    };

    me.HTMLtoBB = function(a) {
      a = a.replace(/[\r\n]+/g, "");
      a = a.replace(/<(hr|br|meta)[^>]*>/gi, "\n");
      a = a.replace(/<img.*?src=["']?([^'"]+)["'][^>]*\/?>/g, "[img]$1[/img]");
      a = me.replaceAll(a, /<(\w+)([^>]*)>([\w\W]*?)<\/\1>/gi, function(
        whole,
        tag,
        attributes,
        innerhtml
      ) {
        var att = {},
          style = "";
        attributes.replace(
          /(color|size|style|href|src)=(['"]?)(.*?)\2/gi,
          function(whole, attr, q, value) {
            att[attr] = value;
          }
        );

        if (att.style) style = att.style;

        tag = tag.toLowerCase();
        if (tag == "script" || tag == "style" || tag == "hr") return;
        if (style.match(/background(\-color)?:[^;]+(rgb\([^\)]+\)|#\s+)/i))
          innerhtml =
            "[bgcolor=#" +
            new JAX.color(RegExp.$2).toHex() +
            "]" +
            innerhtml +
            "[/bgcolor]";
        if (style.match(/text\-align: ?(right|center|left);/i))
          innerhtml = "[align=" + RegExp.$1 + "]" + innerhtml + "[/align]";
        if (style.match(/font\-style: ?italic;/i) || tag == "i" || tag == "em")
          innerhtml = "[I]" + innerhtml + "[/I]";
        if (style.match(/text\-decoration:[^;]*underline;/i) || tag == "u")
          innerhtml = "[U]" + innerhtml + "[/U]";
        if (style.match(/text\-decoration:[^;]*line\-through;/i) || tag == "s")
          innerhtml = "[S]" + innerhtml + "[/S]";
        if (
          style.match(/font\-weight: ?bold;/i) ||
          tag == "strong" ||
          tag == "b"
        )
          innerhtml = "[B]" + innerhtml + "[/B]";
        if (att.size || style.match(/font\-size: ?([^;]+)/i))
          innerhtml =
            "[size=" + (att.size || RegExp.$1) + "]" + innerhtml + "[/size]";
        if (att.color || style.match(/color: ?([^;]+)/i))
          innerhtml =
            "[color=" + (att.color || RegExp.$1) + "]" + innerhtml + "[/color]";
        if (tag == "a" && att.href)
          innerhtml = "[url=" + att.href + "]" + innerhtml + "[/url]";
        if (tag == "ol") innerhtml = "[ol]" + innerhtml + "[/ol]";
        if (tag == "ul") innerhtml = "[ul]" + innerhtml + "[/ul]";
        if (tag.match(/h\d/i))
          innerhtml =
            "[" +
            tag.toLowerCase() +
            "]" +
            innerhtml +
            "[/" +
            tag.toLowerCase() +
            "]";
        if (tag == "li")
          innerhtml = "*" + innerhtml.replace(/[\n\r]+/, "") + "\n";
        if (tag == "p")
          innerhtml = "\n" + (innerhtml == "&nbsp" ? "" : innerhtml) + "\n";
        if (tag == "div") innerhtml = "\n" + innerhtml;
        return innerhtml;
      });
      return a
        .replace(/&gt;/g, ">")
        .replace(/&amp;/g, "&")
        .replace(/&lt;/g, "<")
        .replace(/&nbsp;/g, " ");
    };

    me.switchMode = function(toggle) {
      var t = me.textarea,
        f = me.iframe;
      if (!toggle) {
        t.value = me.HTMLtoBB(me.getSource());
        t.style.display = "";
        f.style.display = "none";
      } else {
        me.setSource(me.BBtoHTML(t.value));
        t.style.display = "none";
        f.style.display = "";
      }
      me.mode = toggle;
    };

    me.submit = function() {
      if (me.mode) {
        me.switchMode(0);
        me.switchMode(1);
      }
    };
    setTimeout(function() {
      me.setSource(me.BBtoHTML(textarea.value));
      me.switchMode(me.mode);
    }, 100);
    return me;
  };
  this.editor.setSelection = function(t, stuff) {
    var scroll = t.scrollTop;
    if (JAX.browser.ie) {
      t.focus();
      document.selection.createRange().text = stuff;
    } else {
      var s = t.selectionStart,
        e = t.selectionEnd;
      t.value = t.value.substring(0, s) + stuff + t.value.substr(e);
      t.selectionStart = s + stuff.length;
      t.selectionEnd = s + stuff.length;
    }
    t.focus();
    t.scrollTop = scroll;
  };

  /********************************************************************/

  this.scrollTo = function(pos, el) {
    //make this animate/not animate later based on preferences
    var dB = document.body,
      el = el || (JAX.browser.chrome ? dB : dE),
      screenrel = parseFloat(dB.clientHeight) - parseFloat(dE.clientHeight),
      top = parseFloat(el.scrollTop),
      pos = screenrel < pos ? screenrel : pos,
      diff = pos - top;
    el.scrollTop += diff;
    /*me={el:el,pos:top,diff:diff,step:1,steps:1} //had this animate once, but now it's just annoying
    me.interval=setInterval(function(){
      me.step++
      (me.el).scrollTop=(me.pos+me.diff*Math.pow(me.step/me.steps,3));
      if(me.step>=me.steps) {clearInterval(me.interval);}
     },30)
    me.then=function(a){
     me.onend=a
    }
    return me*/
  };

  /********************************************************************/

  this.drag = function() {
    var me = this;
    me.priv = {
      start: function(e, t, handle) {
        e = new JAX.event(e).cancel().stopBubbling();
        var el = t || this,
          s = JAX.el.getComputedStyle(el),
          highz = JAX.el.getHighestZIndex();
        if (me._nochild && (e.srcElement || e.target) != (handle || el)) return;
        if (el.getAttribute("draggable") == "false") return;
        me.sess = {
          el: el,
          mx: parseInt(e.pageX),
          my: parseInt(e.pageY),
          ex: parseInt(s.left) || 0,
          ey: parseInt(s.top) || 0,
          info: {},
          bc: JAX.el.getCoordinates(el)
        };
        me.sess.zIndex = el.style.zIndex;
        if (!me.sess.zIndex || Number(me.sess.zIndex) < highz - 1)
          el.style.zIndex = highz;
        if (typeof me.onstart == "function") {
          var sess = me.sess;
          sess.droptarget = me.priv.testdrops(sess.mx, sess.my);
          me.onstart(sess);
        }
        document.onmousemove = me.priv.drag;
        me.priv.drag(e);
        document.onmouseup = me.priv.drop;
      },
      drag: function(e) {
        e = new JAX.event(e).cancel();
        var s = me.sess.el.style,
          sess,
          tmp = false,
          tx,
          ty,
          tmp2;
        var tx,
          ty,
          mx = (tx = parseInt(e.pageX)),
          my = (ty = parseInt(e.pageY)),
          left = me.sess.ex + mx - me.sess.mx,
          top = me.sess.ey + my - me.sess.my,
          b = me.bounds;
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
        tmp = JAX.el.getCoordinates(me.sess.el);
        if (me.useElCoord) {
          tx = tmp.x;
          ty = tmp.y;
        }
        tmp = (sess = me.sess.info).droptarget;
        me.sess.info = sess = {
          left: left,
          top: top,
          e: e,
          el: me.sess.el,
          mx: mx,
          my: my,
          droptarget: me.priv.testdrops(tx, ty),
          dx: mx - (sess.mx || mx),
          dy: my - (sess.my || my),
          self: me,
          sx: me.sess.ex,
          sy: me.sess.ey
        };
        if (typeof me.ondrag == "function") me.ondrag(sess);
        if (
          typeof me.ondragover == "function" &&
          sess["droptarget"] &&
          tmp != sess["droptarget"]
        )
          me.ondragover(sess);
        if (
          typeof me.ondragout == "function" &&
          tmp &&
          sess["droptarget"] != tmp
        ) {
          tmp2 = sess["droptarget"];
          sess["droptarget"] = tmp;
          me.ondragout(sess);
          sess["droptarget"] = tmp2;
        }
      },
      drop: function() {
        document.onmousemove = document.onmouseup = function() {};
        if (typeof me.ondrop == "function") me.ondrop(me.sess.info);
        if (!me._autoz) me.sess.el.style.zIndex = me.sess.zIndex;
        return true;
      },
      testdrops: function(a, b) {
        var x,
          d = me.droppables,
          z,
          r = false,
          max = [9999, 9999];
        if (!d) return r;
        for (x = 0; x < d.length; x++) {
          if (d[x] == me.sess.el || JAX.el.isChildOf(d[x], me.sess.el))
            continue;
          z = JAX.el.getCoordinates(d[x]);
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
    };
    me.drops = function(a) {
      this.droppables = a;
      return this;
    };
    me.addDrops = function(a) {
      if (!this.droppables) {
        return this.drops(a);
      }
      this.droppables = this.droppables.concat(a);
      return this;
    };
    me.addListener = function(a) {
      extend(this, a);
      return this;
    };
    me.apply = function(el, t) {
      var x;
      if (el[0]) {
        for (x = 0; x < el.length; x++) me.apply(el[x]);
        return me;
      }
      var pos = JAX.el.getComputedStyle(el, "");
      pos = pos.position;
      if (!pos || pos == "static") el.style.position = "relative";
      (t || el).onmousedown = t
        ? function(e) {
            me.priv.start(e, el, this);
          }
        : me.priv.start;
      return this;
    };
    me.boundingBox = function(x, y, w, h) {
      this.bounds = [x, y, w, h];
      return this;
    };
    me.autoZ = function() {
      this._autoz = true;
      return this;
    };
    me.noChildActivation = function() {
      this._nochild = true;
      return this;
    };
    me.reset = function(el, zero) {
      if (!el) el = me.sess.el;
      if (zero) {
        el.style.top = el.style.left = 0;
      } else {
        el.style.top = me.sess.ey + "px";
        el.style.left = me.sess.ex + "px";
        el.style.zIndex = me.sess.zIndex;
      }
      return me;
    };
  };

  /********************************************************************/

  this.sortable = function(a, b) {
    var me = this;
    b = b || {};
    me.options = b;
    JAX.drag.apply(me);
    me.coords = [];
    me.elems = a;
    if (b.vertical) me.bounds = [0, -Infinity, 0, Infinity];
    me.ondrop = function(a) {
      if (me.change) me.coords = [];
      me.change = 0;
      var s = a.el.style;
      s.top = s.left = 0;
      if (typeof me.onend == "function") me.onend(a);
    };
    me.ondrag = function(a) {
      var x,
        y,
        d = me.elems,
        dl = d.length,
        pos = 0,
        c,
        cel = JAX.el.getCoordinates(a.el),
        c2,
        ch = false,
        ov = me.options.vertical || 0,
        oh = me.options.horizontal || 0,
        index;
      if (!me.coords.length) {
        for (x = 0; x < dl; x++) me.coords.push(JAX.el.getCoordinates(d[x]));
      }
      for (x = 0; x < dl; x++) {
        if (a.el == d[x]) {
          index = x;
          break;
        }
        c = me.coords[x];
        if (
          ch === false &&
          (ov ? a.my < c.yh && a.dy < 0 : a.mx < c.xw && a.my < c.yh)
        ) {
          JAX.el.insertBefore(a.el, d[x]);
          ch = x;
        }
      }
      if (ch === false) {
        for (x = dl - 1; x >= index; x--) {
          if (a.el == d[x]) continue;
          c = me.coords[x];
          if (ov ? a.my > c.y && a.dy > 0 : a.mx > c.x && a.my > c.y) {
            JAX.el.insertAfter(a.el, d[x]);
            if (d.swap) me.elems = d.swap(index, x);
            ch = 1;
            break;
          }
        }
      } else if (d.swap) me.elems = d.swap(index, ch);
      if (ch !== false) {
        me.coords = [];
        me.change = 1;
        c2 = JAX.el.getCoordinates(a.el);
        me.sess.ex -= c2.x - cel.x;
        me.sess.ey -= c2.y - cel.y;
        me.priv.drag(a.e);
      }
      return false;
    };
    for (var x = 0; x < a.length; x++) {
      me.apply(a[x], typeof b.handle == "function" ? b.handle(a[x]) : null);
    }
  };
  /*******************************************************************/
  this.sortableTree = function(tree, prefix, formfield) {
    var tmp = $$("li", tree),
      x,
      items = [],
      seperators = [],
      all = [],
      drag;
    if (formfield) formfield = $(formfield);
    for (x = 0; x < tmp.length; x++)
      if (tmp[x].className != "title") items.push(tmp[x]);

    function parsetree(tree) {
      var nodes = tree.getElementsByTagName("li");
      var order = {},
        node,
        sub,
        gotsomethin = 0;
      for (var x = 0; x < nodes.length; x++) {
        node = nodes[x];
        if (node.className != "seperator" && node.parentNode == tree) {
          gotsomethin = 1;
          sub = node.getElementsByTagName("ul")[0];
          order["_" + node.id.substr(prefix.length)] =
            sub != undefined ? parsetree(sub) : 1;
        }
      }
      return gotsomethin ? order : 1;
    }

    for (x = 0; x < items.length; x++) {
      tmp = document.createElement("li");
      tmp.className = "seperator";
      seperators.push(tmp);
      JAX.el.insertBefore(tmp, items[x]);
    }

    drag = new JAX.drag().noChildActivation();
    drag.drops(seperators.concat(items)).addListener({
      ondragover: function(a) {
        a.droptarget.style.border = "1px solid #000";
      },
      ondragout: function(a) {
        a.droptarget.style.border = "none";
      },
      ondrop: function(a) {
        var next = a.droptarget.nextSibling,
          tmp,
          tmp2;
        var parentlock = a.el.className == "parentlock";
        var nofirstlevel = a.el.className == "nofirstlevel";
        if (a.droptarget) a.droptarget.style.border = "none";
        if (a.droptarget.className == "seperator") {
          if (parentlock && a.droptarget.parentNode != a.el.parentNode)
            return drag.reset(a.el, 1);
          if (nofirstlevel && a.droptarget.parentNode.className == "tree")
            return drag.reset(a.el, 1);
          if (JAX.el.isChildOf(a.droptarget, a.el) || a.el == next)
            return drag.reset(a.el, 1);
          if (next.className == "spacer") {
            next.parentNode.removeChild(next);
          }
          if (next.className != "spacer")
            JAX.el.insertAfter(a.el.previousSibling, a.droptarget);
          else
            a.el.previousSibling.parentNode.removeChild(a.el.previousSibling);
          JAX.el.insertAfter(a.el, a.droptarget);
        } else if (!parentlock && a.droptarget.tagName == "LI") {
          tmp = a.droptarget.getElementsByTagName("ul")[0];
          if (!tmp) {
            tmp = document.createElement("ul");
            a.droptarget.appendChild(tmp);
          }
          tmp.appendChild(a.el.previousSibling);
          tmp.appendChild(a.el);
          a.droptarget.appendChild(tmp);
        } else {
        }
        drag.reset(a.el, 1);
        if (formfield) formfield.value = JSON.stringify(parsetree(tree));
      }
    });

    all = items.concat(seperators);

    for (x = 0; x < items.length; x++) {
      drag.apply(items[x]);
    }
  };
  /*********************************************************************/

  this.event = function(e) {
    e = e || window.event;
    var dB = document.body;
    switch (e.keyCode) {
      case 13:
        e.ENTER = true;
        break;
      case 37:
        e.LEFT = true;
        break;
      case 38:
        e.UP = true;
        break;
      case 0.39:
        e.RIGHT = true;
        break;
      case 40:
        e.DOWN = true;
        break;
    }
    if (typeof e.srcElement == "undefined") e.srcElement = e.target;
    if (typeof e.pageY == "undefined") {
      e.pageY = e.clientY + (parseInt(dE.scrollTop || dB.scrollTop) || 0);
      e.pageX = e.clientX + (parseInt(dE.scrollLeft || dB.scrollLeft) || 0);
    }
    e.cancel = function() {
      e.returnValue = false;
      if (e.preventDefault) e.preventDefault();
      return e;
    };
    e.stopBubbling = function() {
      if (e.stopPropagation) e.stopPropagation();
      e.cancelBubble = true;
      return e;
    };
    return e;
  };

  /********************************************************************/

  this.ajax = function(s) {
    var me = this;
    me.xmlobj = window.XMLHttpRequest
      ? XMLHttpRequest
      : ActiveXObject("Microsoft.XMLHTTP");
    me.setup = {
      readyState: 4,
      callback: function() {},
      method: "POST"
    };
    me.setup = JAX.extend(me.setup, s);

    me.load = function(a, b, c, d, e) {
      //a=URL b=callback c=send_data d=POST e=type(1=update,2=load new)
      d = d || this.setup.method || "GET";
      if (d) d = "POST";
      if (
        c &&
        Array.isArray(c) &&
        Array.isArray(c[0]) &&
        c[0].length == c[1].length
      )
        c = me.build_query(c[0], c[1]);
      else if (typeof c != "string") c = me.build_query(c);
      var xmlobj = new me.xmlobj();
      if (b) me.setup.callback = b;
      xmlobj.onreadystatechange = function(status) {
        if (xmlobj.readyState == me.setup.readyState) {
          me.setup.callback(xmlobj);
        }
      };
      if (!xmlobj) return false;
      xmlobj.open(d, a, true);
      xmlobj.url = a;
      xmlobj.type = e;
      if (d)
        xmlobj.setRequestHeader(
          "Content-Type",
          "application/x-www-form-urlencoded"
        );
      xmlobj.setRequestHeader("X-JSACCESS", e || 1);
      xmlobj.send(c || null);
      return xmlobj;
    };

    me.loadJSON = function(url, cb) {
      me.load(url, function(x) {
        cb(eval(x.responseText));
      });
    };
  };

  this.ajax.prototype.build_query = function(a, b) {
    var q = "";
    if (b)
      for (x = 0; x < a.length; x++)
        q +=
          encodeURIComponent(a[x]) + "=" + encodeURIComponent(b[x] || "") + "&";
    else
      for (x in a)
        q += encodeURIComponent(x) + "=" + encodeURIComponent(a[x] || "") + "&";
    return q.substring(0, q.length - 1);
  };

  /********************************************************************/

  this.tooltip = function(el) {
    var tooltip = document.getElementById("tooltip_thingy"),
      pos = JAX.el.getCoordinates(el),
      text = (el.oldtitle = el.title.striphtml());
    el.title = "";
    if (!text) return;
    if (!tooltip) {
      tooltip = document.createElement("table");
      var t = tooltip.insertRow(0),
        c = tooltip.insertRow(1),
        b = tooltip.insertRow(2),
        a;

      tooltip.id = "tooltip_thingy";
      tooltip.className = "tooltip";
      t.className = "top";
      c.className = "content";
      b.className = "bottom";
      a = t.insertCell(0);
      a.className = "left";
      a.colSpan = 2;
      a = t.insertCell(1);
      a.className = "right";
      a = c.insertCell(0);
      a.className = "left";
      a = c.insertCell(1);
      a.innerHTML = "default text";
      a = c.insertCell(2);
      a.className = "right";
      a = b.insertCell(0);
      a.className = "left";
      a.colSpan = 2;
      a = b.insertCell(1);
      a.className = "right";
      $("page").appendChild(tooltip);
    }

    tooltip.rows[1].cells[1].innerHTML = text;
    tooltip.style.display = "";
    tooltip.style.top = pos.y - tooltip.clientHeight + "px";
    tooltip.style.left = pos.x + "px";
    tooltip.style.zIndex = JAX.el.getHighestZIndex();
    el.onmouseout = function() {
      el.title = el.oldtitle;
      $("tooltip_thingy").style.display = "none";
    };
  };

  /********************************************************************/

  this.imageResizer = function(imgs) {
    var img, c, mw, mh, p, p2, ih, iw, x, s;
    if (!imgs) return;
    if (!imgs.length) imgs = [imgs];
    for (var x = 0; x < imgs.length; x++) {
      p = p2 = 1;
      (img = imgs[x]),
        (nw = iw = parseInt(img.naturalWidth)),
        (nh = ih = parseInt(img.naturalHeight));
      if (img.madeResized) continue;
      s = JAX.el.getComputedStyle(img);
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
  this.makeResizer = function(iw, nw, ih, nh, img) {
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
      var o = JAX.el.getCoordinates(this);
      e = JAX.event(e);
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
    JAX.el.insertBefore(c, img);
    c.appendChild(img);
  };

  /********************************************************************/

  this.window = function() {
    var me = this,
      zindex = JAX.el.getHighestZIndex();
    me.title = "Title";
    me.wait = true;
    me.content = "Content";
    me.open = false;
    me.useoverlay = false;
    me.minimizable = true;
    me.resize = false;
    me.className = "";
    me.pos = "center";
    me.create = function() {
      if (me.open) return;
      var d1 = d.createElement("div"),
        d2 = d.createElement("div"),
        d3 = d.createElement("div"),
        d4 = d.createElement("div"),
        b1 = d.createElement("div"),
        b2 = d.createElement("div"),
        pos = me.pos,
        x = 0,
        y = 0,
        s;

      me.open = d1;
      if (me.id) d1.id = me.id;
      me.contentcontainer = d3;

      if (me.useoverlay) var tmp = JAX.overlay(1, zindex);
      d1.className = "window" + (me.className ? " " + me.className : "");
      d2.className = "title";
      d3.className = "content";
      if (this.minimizable) {
        b1.innerHTML = "-";
        b1.onclick = me.minimize;
      }
      b2.innerHTML = "X";
      b2.onclick = me.close;
      d4.appendChild(b1);
      d4.appendChild(b2);
      d4.className = "controls";
      d2.innerHTML = me.title;
      d3.innerHTML = me.content;
      d2.appendChild(d4);
      d1.appendChild(d2);
      d1.appendChild(d3);
      d.body.appendChild(d1);

      if (me.resize) {
        var targ = $$(me.resize, d1);
        if (!targ) return alert("Resize target not found");
        targ.style.width = targ.clientWidth + "px";
        targ.style.height = targ.clientHeight + "px";
        var rsize = document.createElement("div");
        rsize.className = "resize";
        d1.appendChild(rsize);
        rsize.style.left = d1.clientWidth - 16 + "px";
        rsize.style.top = d1.clientHeight - 16 + "px";
        new JAX.drag()
          .boundingBox(100, 100, Infinity, Infinity)
          .addListener({
            ondrag: function(a) {
              var w = parseFloat(targ.style.width) + a.dx,
                h = parseFloat(targ.style.height) + a.dy;
              targ.style.width = w + "px";
              if (w < d1.clientWidth - 20) {
                targ.style.width = d1.clientWidth + "px";
              } else {
                rsize.style.left = d1.clientWidth - 16 + "px";
              }
              targ.style.height = h + "px";
            },
            ondrop: function(a) {
              rsize.style.left = d1.clientWidth - 16 + "px";
            }
          })
          .apply(rsize);
        targ.style.width = d1.clientWidth + "px";
        rsize.style.left = d1.clientWidth - 16 + "px";
      }

      s = d1.style;
      s.zIndex = zindex + 5;

      if (me.wait)
        JAX.onImagesLoaded(
          d1.getElementsByTagName("img"),
          function() {
            me.setPosition(pos);
          },
          2000
        );
      else me.setPosition(pos);

      me.drag = new JAX.drag()
        .autoZ()
        .noChildActivation()
        .boundingBox(0, 0, dE.clientWidth - 50, dE.clientHeight - 50)
        .apply(d1, d2);
      d1.close = me.close;
      d1.minimize = me.minimize;
      return d1;
    };
    me.close = function() {
      if (!me.open) return;
      var s = me.open.style;
      if (me.animate && false) {
        //this is broken until further notice
        JAX.sfx(me.open, 10)
          .add("top", s.top, parseFloat(s.top) + 100 + "px")
          .then(function() {
            document.body.removeChild(me.open);
            me.open = null;
          })
          .play();
      } else {
        document.body.removeChild(me.open);
        me.open = null;
      }
      if (me.onclose) me.onclose();
      if (me.useoverlay) JAX.overlay(0);
    };
    me.minimize = function() {
      var c = me.open,
        x,
        w = 0;
      if (JAX.el.hasClass(c, "minimized")) {
        JAX.el.removeClass(c, "minimized");
        c.removeAttribute("draggable");
        me.setPosition(me.oldpos, 0);
      } else {
        c.setAttribute("draggable", "false");
        var wins = $$(".window");
        if (!wins.length) wins = [wins];
        for (x = 0; x < wins.length; x++)
          if (JAX.el.hasClass(wins[x], "minimized"))
            w += parseInt(wins[x].clientWidth);
        me.oldpos = me.getPosition();
        JAX.el.addClass(c, "minimized");
        me.setPosition("bl " + w + " 0", 0);
      }
    };
    me.setPosition = function(pos, animate) {
      var d1 = me.open,
        x = 0,
        y = 0,
        cH = dE.clientHeight,
        cW = dE.clientWidth;
      if ((s = pos.match(/(\d+) (\d+)/))) {
        x = Number(s[1]);
        y = Number(s[2]);
      }
      x = Math.floor(x);
      y = Math.floor(y);
      if (pos.charAt(1) == "r") {
        x = cW - x - d1.clientWidth;
      }
      switch (pos.charAt(0)) {
        case "b":
          y = cH - y - d1.clientHeight;
          break;
        case "c":
          y = (cH - d1.clientHeight) / 2;
          x = (cW - d1.clientWidth) / 2;
          break;
      }
      x = Math.floor(x);
      y = Math.floor(y);

      if (x < 0) x = 0;
      if (y < 0) y = 0;
      d1.style.left = x + "px";
      if (me.animate && animate !== 0) {
        JAX.sfx(d1, 10)
          .add("top", y - 100 + "px", y + "px")
          .play();
      } else d1.style.top = y + "px";
      me.pos = pos;
    };
    this.getPosition = function() {
      var s = this.open.style;
      return "tl " + parseFloat(s.left) + " " + parseFloat(s.top);
    };
  };
  this.window.close = function(win) {
    do {
      if (win.close) {
        win.close();
        break;
      }
      win = win.offsetParent;
    } while (win);
  };

  //scrolling page list functionality
  function scrollpagelist(e) {
    e = JAX.event(e).cancel();
    var d = e.detail || e.wheelDelta;
    d = Math.abs(d) / d;
    if (JAX.browser.chrome) d *= -1;
    var x,
      p = $$("a", this),
      s = parseInt(p[1].innerHTML),
      e = parseInt(p[p.length - 1].innerHTML),
      b = p.length - 2;
    if (JAX.browser.ie) d *= -1;
    if ((d > 0 && s + b < e) || (d < 0 && s > 2))
      for (x = 0; x < b; x++) {
        p[x + 1].href = p[x + 1].href.replace(/\d+$/, x + s + d);
        p[x + 1].innerHTML = s + x + d;
      }
  }
  this.scrollablepagelist = function(pl) {
    if (pl.addEventListener)
      pl.addEventListener("DOMMouseScroll", scrollpagelist, false);
    pl.onmousewheel = scrollpagelist;
  };
  /********************************************************/
  this.makeImageGallery = function(gallery) {
    if (gallery.madeGallery) return;
    gallery.madeGallery = true;
    var controls = document.createElement("div"),
      next = document.createElement("a"),
      prev = document.createElement("a"),
      status = {
        index: 0,
        max: $$("img", gallery).length || 1,
        showNext: function() {
          if (this.index < this.max - 1) this.index++;
          this.update();
        },
        showPrev: function() {
          if (this.index > 0) this.index--;
          this.update();
        },
        update: function() {
          var imgs = $$("img", gallery),
            x,
            img;
          for (x = 0; x < imgs.length; x++) {
            img = imgs[x];
            if (img.madeResized) {
              img = img.parentNode;
            }
            img.style.display = x != this.index ? "none" : "block";
          }
        }
      };
    next.innerHTML = "Next &raquo;";
    next.href = "#";
    next.onclick = function() {
      status.showNext();
      return false;
    };

    prev.innerHTML = "Prev &laquo;";
    prev.href = "#";
    prev.onclick = function() {
      status.showPrev();
      return false;
    };

    status.update();
    controls.appendChild(prev);
    controls.appendChild(document.createTextNode(" "));
    controls.appendChild(next);
    gallery.appendChild(controls);
  };

  /********************************************************/

  this.datepicker = new function() {
    this.months = [
      "January",
      "February",
      "March",
      "April",
      "May",
      "June",
      "July",
      "August",
      "September",
      "October",
      "November",
      "December"
    ];
    this.daysshort = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]; //I don't think I'll need a dayslong ever

    this.init = function(el) {
      var dp = $("datepicker"),
        s,
        c = JAX.el.getCoordinates(el),
        x;
      if (!dp) {
        dp = document.createElement("table");
        dp.id = "datepicker";
        $("page").appendChild(dp);
      }
      s = dp.style;
      s.display = "table";
      s.zIndex = JAX.el.getHighestZIndex();
      s.top = c.yh + "px";
      s.left = c.x + "px";
      s = el.value.split("/");
      if (s.length == 3) {
        this.selectedDate = [
          parseInt(s[2]),
          parseInt(s[0]) - 1,
          parseInt(s[1])
        ];
      } else this.selectedDate = undefined;

      this.el = el;
      this.generate(s[2], s[0] ? parseInt(s[0]) - 1 : undefined, s[1]);
    };

    //month should be 0 for jan, 11 for dec
    this.generate = function(year, month, day) {
      var date = new Date(),
        dp = $("datepicker"),
        row,
        cell,
        x,
        i;
      //date here is today
      if (year == undefined) {
        year = date.getFullYear();
        month = date.getMonth();
        day = date.getDate();
        this.selectedDate = [year, month, day];
      }

      if (month == -1) {
        year--;
        month = 11;
      }
      if (month == 12) {
        year++;
        month = 0;
      }

      this.lastDate = [year, month, day];

      //this date is used to calculate days in month and the day the first is on
      var numdaysinmonth = new Date(year, month + 1, 0).getDate();
      var first = new Date(year, month, 1).getDay();

      date = new Date(year, month, day);
      //generate the table now
      dp.innerHTML = ""; //clear

      //year
      row = dp.insertRow(0);
      cell = row.insertCell(0);
      cell.innerHTML = "<";
      cell.className = "control";
      cell.onclick = function() {
        JAX.datepicker.lastYear();
      };
      cell = row.insertCell(1);
      cell.colSpan = "5";
      cell.className = "year";
      cell.innerHTML = year;
      cell = row.insertCell(2);
      cell.innerHTML = ">";
      cell.className = "control";
      cell.onclick = function() {
        JAX.datepicker.nextYear();
      };

      //month title
      row = dp.insertRow(1);
      cell = row.insertCell(0);
      cell.innerHTML = "<";
      cell.className = "control";
      cell.onclick = function() {
        JAX.datepicker.lastMonth();
      };
      cell = row.insertCell(1);
      cell.colSpan = "5";
      cell.innerHTML = this.months[month];
      cell.className = "month";
      cell = row.insertCell(2);
      cell.innerHTML = ">";
      cell.className = "control";
      cell.onclick = function() {
        JAX.datepicker.nextMonth();
      };

      //weekdays
      row = dp.insertRow(2);
      row.className = "weekdays";
      for (x = 0; x < 7; x++) row.insertCell(x).innerHTML = this.daysshort[x];

      row = dp.insertRow(3);
      //generate numbers
      for (x = 0; x < numdaysinmonth; x++) {
        if (!x) for (i = 0; i < first; i++) row.insertCell(i);
        if ((first + x) % 7 == 0) row = dp.insertRow(dp.rows.length);
        cell = row.insertCell((first + x) % 7);
        cell.onclick = function() {
          JAX.datepicker.insert(this);
        };
        cell.className =
          "day" +
          (year == this.selectedDate[0] &&
          month == this.selectedDate[1] &&
          x + 1 == this.selectedDate[2]
            ? " selected"
            : "");
        cell.innerHTML = x + 1;
      }
    };

    this.lastYear = function() {
      var l = this.lastDate;
      this.generate(l[0] - 1, l[1], l[2]);
    };
    this.nextYear = function() {
      var l = this.lastDate;
      this.generate(l[0] + 1, l[1], l[2]);
    };
    this.lastMonth = function() {
      var l = this.lastDate;
      this.generate(l[0], l[1] - 1, l[2]);
    };
    this.nextMonth = function() {
      var l = this.lastDate;
      this.generate(l[0], l[1] + 1, l[2]);
    };

    this.insert = function(cell) {
      var l = this.lastDate;
      this.el.value = l[1] + 1 + "/" + cell.innerHTML + "/" + l[0];
      this.hide();
    };
    this.hide = function() {
      $("datepicker").style.display = "none";
    };
  }();
}();

var Uploader = new function() {
  this.uploaders = [];
  this.listenerHandler = function(id, action, args) {
    var tmp;
    //moving arguments around
    switch (action) {
      case "addfile":
        args[0].id = args[1];
        args = args[0];
        args.upload = function(url) {
          Uploader.upload(id, this, url);
        };
        args = [args];
        break;
      case "startupload":
        args[0].id = args[1];
        args = [args[0]];
        break;
      case "progress":
        args[0].id = args[1];
        args.splice(1, 1);
        break;
      case "error":
        args[2].id = args.pop();
        break;
      default:
        if (!args.length) args = [args];
        break;
    }
    if (this.uploaders[id] && this.uploaders[id][action])
      this.uploaders[id][action].apply(this.uploaders[id], args);
  };
  this.createButton = function() {
    var d = document.createElement("div");
    d.className = "uploadbutton";
    d.innerHTML = "Add File(s)";
    return [d, this.create(d)];
  };
  this.create = function(el, w, h, url) {
    var nid = this.uploaders.length;
    var swf = JAX.SWF("Script/uploader.swf", "uploader" + nid, {
        width: w || "100%",
        height: h || "100%",
        allowScriptAccess: "sameDomain",
        wmode: "transparent",
        flashvars: "id=" + nid
      }),
      s = swf.style;
    s.position = "absolute";
    s.left = "0px";
    s.top = "0px";
    el.style.position = "relative";
    el.appendChild(swf);
    this.uploaders.push([]);
    this.uploaders[nid].flashObj = swf;
    this.uploaders[nid].id = nid;
    return this.uploaders[nid];
  };
  this.upload = function(nid, fileobj, url) {
    this.uploaders[nid].flashObj.upload(fileobj.id, url);
  };
}();

var Sound = new function() {
  this.queue = this.loadedSounds = [];
  this.ready = function() {
    var me = this;
    setTimeout(function() {
      if (me.isready) return; //page redraws
      var q = me.queue;
      me.flashObject =
        window.soundLoader && window.soundLoader.playSound
          ? window.soundLoader
          : document.soundLoader;
      if (!me.flashObject || !me.flashObject.loadSound) return; //no flash installed
      if (q.length)
        for (var x = 0; x < q.length; x++)
          me.flashObject.loadSound(q[x][0], q[x][1], q[x][2] || false);
      me.queue = [];
      me.isready = true;
    }, 100);
  };
  this.load = function(title, file, autoplay) {
    this.loadedSounds[title] = file;
    if (this.isready)
      this.flashObject.loadSound(title, file, autoplay || false);
    else this.queue.push([title, file, autoplay || false]);
  };
  this.loadAndPlay = function(title, file) {
    if (this.loadedSounds[title] == file) this.play(title);
    else this.load(title, file, true);
  };
  this.play = function(title) {
    if (this.flashObject) this.flashObject.playSound(title);
  };
  this.addFlash = function() {
    document.body.appendChild(
      JAX.SWF("Script/soundLoader.swf", "soundLoader", {
        hidden: "true",
        allowScriptAccess: "sameDomain",
        width: 0,
        height: 0
      })
    );
  };
}();

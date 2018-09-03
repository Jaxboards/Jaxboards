var window = (function () {
  'use strict';

  class ajax {
    constructor(s) {
      this.xmlobj = window.XMLHttpRequest;
      this.setup = {
        readyState: 4,
        callback: function() {},
        method: "POST",
        ...s
      };
    }

    load(a, b, c, d, e) {
      // a=URL b=callback c=send_data d=POST e=type(1=update,2=load new)
      d = d || this.setup.method || "GET";
      if (d) d = "POST";
      if (
        c &&
        Array.isArray(c) &&
        Array.isArray(c[0]) &&
        c[0].length == c[1].length
      ) {
        c = this.build_query(c[0], c[1]);
      } else if (typeof c != "string") c = this.build_query(c);
      var xmlobj = new this.xmlobj();
      if (b) this.setup.callback = b;
      xmlobj.onreadystatechange = function(status) {
        if (xmlobj.readyState == this.setup.readyState) {
          this.setup.callback(xmlobj);
        }
      };
      if (!xmlobj) return false;
      xmlobj.open(d, a, true);
      xmlobj.url = a;
      xmlobj.type = e;
      if (d) {
        xmlobj.setRequestHeader(
          "Content-Type",
          "application/x-www-form-urlencoded"
        );
      }
      xmlobj.setRequestHeader("X-JSACCESS", e || 1);
      xmlobj.send(c || null);
      return xmlobj;
    }

    build_query(a, b) {
      var q = "";
      if (b) {
        for (x = 0; x < a.length; x++) {
          q +=
            encodeURIComponent(a[x]) + "=" + encodeURIComponent(b[x] || "") + "&";
        }
      } else {
        for (x in a) {
          q += encodeURIComponent(x) + "=" + encodeURIComponent(a[x] || "") + "&";
        }
      }
      return q.substring(0, q.length - 1);
    }
  }

  const userAgent = navigator.userAgent;

  var Browser = {
    chrome: !!userAgent.match(/chrome/i),
    ie: !!userAgent.match(/msie/i),
    iphone: !!userAgent.match(/iphone/i),
    mobile: !!userAgent.match(/mobile/i),
    n3ds: !!userAgent.match(/nintendo 3ds/),
    firefox: !!userAgent.match(/firefox/i),
    safari: !!userAgent.match(/safari/i)
  };

  const ordsuffix = function(a) {
    return (
      a +
      (Math.round(a / 10) == 1 ? "th" : ["", "st", "nd", "rd"][a % 10] || "th")
    );
  };

  const date = function(a) {
    var old = new Date();
    var now = new Date();
    var fmt;
    var hours;
    var mins;
    var delta;
    var ampm;
    var yday = new Date();
    var dstr;
    var months = [
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
    old.setTime(a * 1000); // setTime uses milliseconds, we'll be using UNIX Times as the argument
    hours = old.getHours() % 12;
    hours = `${hours || 12}`;
    ampm = hours >= 12 ? "pm" : "am";
    mins = `${old.getMinutes()}`.padStart(2, "0");
    dstr = `${old.getDate()} ${old.getMonth()} ${old.getFullYear()}`;
    delta = (now.getTime() - old.getTime()) / 1000;
    if (delta < 90) {
      fmt = "a minute ago";
    } else if (delta < 3600) {
      fmt = `${Math.round(delta / 60)} minutes ago`;
    } else if (
      now.getDate() + " " + now.getMonth() + " " + now.getFullYear() ==
      dstr
    ) {
      fmt = `Today @ ${hours}:${mins} ${ampm}`;
    } else if (
      yday.getDate() + " " + yday.getMonth() + " " + yday.getFullYear() ==
      dstr
    ) {
      fmt = `Yesterday @ ${hours}:${mins} ${ampm}`;
    } else {
      fmt =
        `${months[old.getMonth()]} ${ordsuffix(old.getDate())}, ${old.getFullYear()} @ ${hours}:${mins} ${ampm}`;
    }
    return fmt;
  };

  const smalldate = function(a) {
    var d = new Date();
    d.setTime(a * 1000);
    const hours = d.getHours();
    const ampm = hours >= 12 ? "pm" : "am";
    hours %= 12;
    hours = hours || 12;
    const minutes = `${d.getMinutes()}`.padStart(2, "0");
    const month = d.getMonth() + 1;
    const day = `${d.getDate()}`.padStart(2, "0");
    const year = d.getFullYear();
    return `${hours}:${minutes}${ampm}, ${month}/${day}/${year}`;
  };

  class Color {
    constructor (a) {
      var tmp;
      var x;
      if (a.charAt && a.charAt(0) == "#") a = a.substr(1);
      // RGB
      if (typeof a == "object") this.rgb = a;
      else if (a.match && (tmp = a.match(/^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i))) {
        tmp[1] = parseFloat(tmp[1]);
        tmp[2] = parseFloat(tmp[2]);
        tmp[3] = parseFloat(tmp[3]);
        tmp.shift();
        this.rgb = tmp;
        // HEX
      } else if (a.match && !a.match(/[^\da-fA-F]/)) {
        if (a.length == 3) {
          a =
            a.charAt(0) +
            a.charAt(0) +
            a.charAt(1) +
            a.charAt(1) +
            a.charAt(2) +
            a.charAt(2);
        }
        if (a.length != 6) this.rgb = [0, 0, 0];
        else {
          this.rgb = [];
          for (x = 0; x < 3; x++) this.rgb[x] = parseInt(a.substr(x * 2, 2), 16);
        }
      } else this.rgb = [0, 0, 0];
    }

    invert() {
      this.rgb = [255 - this.rgb[0], 255 - this.rgb[1], 255 - this.rgb[2]];
      return this;
    }

    toRGB() {
      return this.rgb;
    }

    toHex() {
      if (!this.rgb) return false;
      var tmp2;
      var tmp = "";
      var x;
      var hex = "0123456789ABCDEF";
      for (x = 0; x < 3; x++) {
        tmp2 = this.rgb[x];
        tmp +=
          hex.charAt(Math.floor(tmp2 / 16)) + hex.charAt(Math.floor(tmp2 % 16));
      }
      return tmp;
    }
  }

  const getComputedStyle = function(a, b) {
    if (!a) return false;
    if (a.currentStyle) return a.currentStyle;
    else if (window.getComputedStyle) return window.getComputedStyle(a, b);
    return false;
  };

  const getCoordinates = function(a) {
    var x = 0;
    var y = 0;
    var h = parseInt(a.offsetHeight) || 0;
    var w = parseInt(a.offsetWidth) || 0;
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
  };

  const isChildOf = function(a, b) {
    while ((a = a.parentNode)) if (a == b) return true;
    return false;
  };

  const insertBefore = function(a, b) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode.insertBefore(a, b);
  };

  const insertAfter = function(a, b) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode.insertBefore(a, b.nextSibling);
  };

  const replace = function(a, b) {
    insertBefore(b, a);
    if (a.parentNode) a.parentNode.removeChild(a);
  };

  const getHighestZIndex = function() {
    var a = document.getElementsByTagName("*");
    var l = a.length;
    var x;
    var max = 0;
    for (x = 0; x < l; x++) {
      if (a[x].style.zIndex && Number(a[x].style.zIndex) > max) {
        max = Number(a[x].style.zIndex);
      }
    }
    return max + 1;
  };

  var el = {
    getComputedStyle,
    getCoordinates,
    isChildOf,
    insertBefore,
    insertAfter,
    replace,
    getHighestZIndex,
  };

  const months = [
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

  const daysshort = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]; // I don't think I'll need a dayslong ever

  class DatePicker {
    constructor(el$$1) {
      var dp = document.querySelector("#datepicker");
      var s;
      var c = getCoordinates(el$$1);
      if (!dp) {
        dp = document.createElement("table");
        dp.id = "datepicker";
        document.querySelector("#page").appendChild(dp);
      }
      s = dp.style;
      s.display = "table";
      s.zIndex = getHighestZIndex();
      s.top = c.yh + "px";
      s.left = c.x + "px";
      s = el$$1.value.split("/");
      if (s.length == 3) {
        this.selectedDate = [
          parseInt(s[2]),
          parseInt(s[0]) - 1,
          parseInt(s[1])
        ];
      } else this.selectedDate = undefined;

      this.el = el$$1;
      this.generate(s[2], s[0] ? parseInt(s[0]) - 1 : undefined, s[1]);
    };

    // month should be 0 for jan, 11 for dec
    generate(year, month, day) {
      var date = new Date();
      var dp = document.querySelector("#datepicker");
      var row;
      var cell;
      var x;
      var i;
      // date here is today
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

      // this date is used to calculate days in month and the day the first is on
      var numdaysinmonth = new Date(year, month + 1, 0).getDate();
      var first = new Date(year, month, 1).getDay();

      date = new Date(year, month, day);
      // generate the table now
      dp.innerHTML = ""; // clear

      // year
      row = dp.insertRow(0);
      cell = row.insertCell(0);
      cell.innerHTML = "<";
      cell.className = "control";
      cell.onclick = function() {
        this.lastYear();
      };
      cell = row.insertCell(1);
      cell.colSpan = "5";
      cell.className = "year";
      cell.innerHTML = year;
      cell = row.insertCell(2);
      cell.innerHTML = ">";
      cell.className = "control";
      cell.onclick = function() {
        this.nextYear();
      };

      // month title
      row = dp.insertRow(1);
      cell = row.insertCell(0);
      cell.innerHTML = "<";
      cell.className = "control";
      cell.onclick = function() {
        this.lastMonth();
      };
      cell = row.insertCell(1);
      cell.colSpan = "5";
      cell.innerHTML = months[month];
      cell.className = "month";
      cell = row.insertCell(2);
      cell.innerHTML = ">";
      cell.className = "control";
      cell.onclick = function() {
        this.nextMonth();
      };

      // weekdays
      row = dp.insertRow(2);
      row.className = "weekdays";
      for (x = 0; x < 7; x++) row.insertCell(x).innerHTML = daysshort[x];

      row = dp.insertRow(3);
      // generate numbers
      for (x = 0; x < numdaysinmonth; x++) {
        if (!x) for (i = 0; i < first; i++) row.insertCell(i);
        if ((first + x) % 7 == 0) row = dp.insertRow(dp.rows.length);
        cell = row.insertCell((first + x) % 7);
        cell.onclick = function() {
          this.insert(this);
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
    }

    lastYear() {
      var l = this.lastDate;
      this.generate(l[0] - 1, l[1], l[2]);
    }
    nextYear() {
      var l = this.lastDate;
      this.generate(l[0] + 1, l[1], l[2]);
    }
    lastMonth() {
      var l = this.lastDate;
      this.generate(l[0], l[1] - 1, l[2]);
    }
    nextMonth() {
      var l = this.lastDate;
      this.generate(l[0], l[1] + 1, l[2]);
    }

    insert(cell) {
      var l = this.lastDate;
      this.el.value = l[1] + 1 + "/" + cell.innerHTML + "/" + l[0];
      this.hide();
    }
  }

  // Static methods
  DatePicker.init = function(el$$1) {
    return new DatePicker(el$$1);
  };
  DatePicker.hide = function() {
    document.querySelector("#datepicker").style.display = "none";
  };

  /**
   * This method adds some decoration to the default browser event.
   * This can probably be replaced with something more modern.
   */
  function Event(e) {
    const dB = document.body;
    const dE = document.documentElement;
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
  }

  class Animation {
    constructor(el$$1, steps, delay, loop) {
      this.el = el$$1;
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

  // This file is just a dumping ground until I can find better homes for these

  const assign = function(a, b) {
    Object.assign(a, b);
  };

  const gracefulDegrade = function(a) {
    if (typeof RUN != "undefined") RUN.updateDates();
    var tmp;
    var links = a.getElementsByTagName("a");
    var l = links.length;
    var x;
    for (x = 0; x < l; x++) {
      if (links[x].href) {
        if (links[x].getAttribute("href").charAt(0) == "?") {
          if (links[x].onclick) links[x].oldclick = links[x].onclick;
          links[x].onclick = function() {
            if (!this.oldclick || this.oldclick() != false) {
              RUN.stream.location(this.getAttribute("href"));
            }
            return false;
          };
        } else if (links[x].getAttribute("href").substr(0, 4) == "http") {
          links[x].target = "_BLANK";
        }
      }
    }
    convertSwitches(a.querySelectorAll(".switch"));

    var bbcodeimgs = document.querySelectorAll(".bbcodeimg");
    if (bbcodeimgs) {
      JAX.onImagesLoaded(
        bbcodeimgs,
        function() {
          // resizer on large images
          JAX.imageResizer(bbcodeimgs);

          // handle image galleries
          var galleries = document.querySelectorAll(".image_gallery");
          for (x = 0; x < galleries.length; x++) {
            JAX.makeImageGallery(galleries[x]);
          }
        },
        2000
      );
    }

    if ((tmp = a.querySelectorAll(".pages"))) {
      for (x = 0; x < tmp.length; x++) JAX.scrollablepagelist(tmp[x]);
    }

    if ((tmp = a.querySelectorAll(".date"))) {
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
  };

  const convertSwitches = function(switches) {
    var x;
    var l = switches.length;
    var s;
    var t;
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
      insertAfter(t, s);
    }
  };

  const checkAll = function(checkboxes, value) {
    for (var x = 0; x < checkboxes.length; x++) checkboxes[x].checked = value;
  };

  const onImagesLoaded = function(imgs, f, timeout) {
    var x;
    var dbj = {
      imgs: [],
      imgsloaded: 1,
      called: false,
      force: function() {
        if (!dbj.called) f();
      }
    };
    dbj.callback = function(event) {
      if (dbj.called) {
        return;
      }
      var x = dbj.imgs.includes(this.src);
      if (x === false) {
        return;
      }
      dbj.imgs.splice(x, 1);
      if (dbj.imgs.length == 0) {
        f();
        dbj.called = true;
      }
    };
    for (x = 0; x < imgs.length; x++) {
      if (dbj.imgs.includes(imgs[x].src) === false && !imgs[x].loaded) {
        dbj.imgs.push(imgs[x].src);
        imgs[x].addEventListener("onload", dbj.callback);
        imgs[x].src = imgs[x].src;
      }
    }
    if (!imgs.length) {
      f();
      dbj.called = true;
    } else if (timeout) setTimeout(dbj.force, timeout);
  };

  const handleTabs = function(e, a, f) {
    var e = e || window.event;
    var el$$1 = e.target || e.srcElement;
    var act;
    if (el$$1.tagName.toLowerCase() != "a") return;
    if (f) el$$1 = f(el$$1);
    act = a.querySelector(".active");
    if (act) act.className = "";
    el$$1.className = "active";
    el$$1.blur();
  };

  const toggle = function(a) {
    if (a.style.display == "none") a.style.display = "";
    else a.style.display = "none";
  };

  const collapse = function(a) {
    var s = a.style;
    var fh = a.getAttribute("fullHeight");
    var b = a.parentNode;
    s.overflow = "hidden";
    if (s.height == "0px") {
      new Animation(a, 5, 10, 0)
        .add("height", "0px", fh)
        .then(function() {
          b.classList.remove("collapsed");
        })
        .play();
    } else {
      if (!fh) {
        fh = (a.clientHeight || a.offsetHeight) + "px";
        a.setAttribute("fullHeight", fh);
      }
      new Animation(a, 5, 10, 0)
        .add("height", fh, "0px")
        .then(function() {
          b.classList.add("collapsed");
        })
        .play();
    }
  };

  const toggleOverlay = function(show) {
    const dE = document.documentElement;
    show = parseInt(show);
    var ol = document.getElementById("overlay");
    var s;
    if (ol) {
      s = ol.style;
      s.zIndex = getHighestZIndex();
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

  const scrollTo = function(pos, el$$1) {
    // make this animate/not animate later based on preferences
    const dB = document.body;
    const dE = document.documentElement;
    var el$$1 = el$$1 || (Browser.chrome ? dB : dE);
    var screenrel = parseFloat(dB.clientHeight) - parseFloat(dE.clientHeight);
    var top = parseFloat(el$$1.scrollTop);
    var pos = screenrel < pos ? screenrel : pos;
    var diff = pos - top;
    el$$1.scrollTop += diff;
    /* me={el:el,pos:top,diff:diff,step:1,steps:1} //had this animate once, but now it's just annoying
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
  }
  class Drag$1 {
    constructor() {

    }

    start(event, t, handle) {
      e = new Event(event).cancel().stopBubbling();
      var el$$1 = t || this;
      var s = getComputedStyle(el$$1);
      var highz = getHighestZIndex();
      if (this._nochild && (e.srcElement || e.target) != (handle || el$$1)) return;
      if (el$$1.getAttribute("draggable") == "false") return;
      this.sess = {
        el: el$$1,
        mx: parseInt(e.pageX),
        my: parseInt(e.pageY),
        ex: parseInt(s.left) || 0,
        ey: parseInt(s.top) || 0,
        info: {},
        bc: getCoordinates(el$$1),
        zIndex: el$$1.style.zIndex,
      };
      if (!this.sess.zIndex || Number(this.sess.zIndex) < highz - 1) {
        el$$1.style.zIndex = highz;
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

    apply(el$$1, t) {
      var x;
      if (el$$1[0]) {
        for (x = 0; x < el$$1.length; x++) me.apply(el$$1[x]);
        return me;
      }
      var pos = getComputedStyle(el$$1, "");
      pos = pos.position;
      if (!pos || pos == "static") el$$1.style.position = "relative";
      (t || el$$1).onmousedown = t
        ? function(e) {
            me.start(e, el$$1, this);
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

    reset(el$$1, zero) {
      if (!el$$1) el$$1 = this.sess.el;
      if (zero) {
        el$$1.style.top = el$$1.style.left = 0;
      } else {
        el$$1.style.top = this.sess.ey + "px";
        el$$1.style.left = this.sess.ex + "px";
        el$$1.style.zIndex = this.sess.zIndex;
      }
      return me;
    }
  }

  class Editor {
    constructor(textarea, iframe) {
      if (!iframe.timedout) {
        iframe.timedout = true;
        setTimeout(function() {
          new Editor(textarea, iframe);
        }, 100);
        return;
      }
      if (iframe.editor) return;
      this.iframe = iframe;
      iframe.editor = this;
      iframe.className = "editorframe";
      this.mode =
        Browser.mobile || Browser.n3ds ? 0 : globalsettings.wysiwyg; // 1 for html editing mode, 0 for textarea mode
      this.mode = this.mode || 0;
      this.textarea = textarea;
      this.window = iframe.contentWindow;
      this.doc = iframe.contentWindow.document;

      var cs = getComputedStyle(this.textarea);
      var body = this.doc.getElementsByTagName("body")[0];
      if (body && cs) {
        body.style.backgroundColor = cs.backgroundColor;
        body.style.color = cs.color;
        body.style.borderColor = "#FFF";
      }

      this.doc.designMode = "on";

      this.editbar = document.createElement("div");
      this.buildEditBar();

      this.editbar.style.width = textarea.clientWidth + 2 + "px";
      iframe.style.width = textarea.clientWidth + "px";
      iframe.style.height = textarea.clientHeight + "px";

      insertBefore(this.editbar, textarea);

      // Set the source and initialize the editor
      //
      this.setSource("<div></div>");
      setTimeout(function() {
        this.setSource(this.BBtoHTML(textarea.value));
        this.switchMode(this.mode);
      }, 100);
      return me;
    }

    buildEditBar() {
      this.editbar.className = "editbar";
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
      ];

      var cmddesc = [
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
      ];

      var l = cmds.length;
      var a;
      var x;
      for (x = 0; x < l; x++) {
        a = document.createElement("a");
        a.className = cmds[x];
        a.title = cmddesc[x];
        a.href = "javascript:void(0)";
        a.unselectable = "on";
        a.onclick = event => this.editbarCommand(event, this.className);
        this.editbar.appendChild(a);
      }
    }

    editbarCommand(e, cmd) {
      e = Event(e).cancel();

      switch(cmd) {
        case "forecolor":
        case "backcolor":
          this.showColors(e.pageX, e.pageY, cmd);
          break;
        case "c_smileys":
          this.showEmotes(e.pageX, e.pageY);
          break;
        case "c_switcheditmode":
          this.switchMode(Math.abs(this.mode - 1));
          break;
        default:
          this.cmd(cmd);
          break;
        }
    }

    showEmotes(x, y) {
      var emotewin = this.emoteWindow;
      if (!emotewin) {
        this.createEmoteWindow.x = x;
        this.createEmoteWindow.y = y;
        new ajax().load("/misc/emotes.php?json", this.createEmoteWindow);
        return;
      }
      if (emotewin.style.display == "none") {
        emotewin.style.display = "";
        emotewin.style.top = y + "px";
        emotewin.style.left = x + "px";
      } else {
        this.hideEmotes();
      }
    }

    hideEmotes() {
      if (this.emoteWindow) {
        this.emoteWindow.style.display = "none";
      }
    }

    createEmoteWindow(xml) {
      var rs = JSON.parse(xml.responseText);
      var x;
      var emotewin = document.createElement("div");
      var r;
      emotewin.className = "emotewin";
      for (x = 0; x < rs[0].length; x++) {
        r = document.createElement("a");
        r.href = "javascript:void(0)";
        r.emotetext = rs[0][x];
        r.onclick = () => {
          this.cmd("inserthtml", this.emotetext);
          this.hideEmotes();
        };
        r.innerHTML = rs[1][x] + " " + rs[0][x];
        emotewin.appendChild(r);
      }
      emotewin.style.position = "absolute";
      emotewin.style.display = "none";
      this.emoteWindow = emotewin;
      document.querySelector("#page").appendChild(emotewin);
      this.showEmotes(this.createEmoteWindow.x, this.createEmoteWindow.y);
    }

    colorHandler(cmd) {
      this.cmd(cmd, this.style.backgroundColor);
      this.hideColors();
    }

    showColors(posx, posy, cmd) {
      if (this.colorWindow && this.colorWindow.style.display != "none") {
        return this.hideColors();
      }
      var colorwin = this.colorWindow;
      var colors = [
        "FFFFFF",
        "AAAAAA",
        "000000",
        "FF0000",
        "00FF00",
        "0000FF",
        "FFFF00",
        "00FFFF",
        "FF00FF"
      ];
      var l = colors.length;
      var sq = Math.ceil(Math.sqrt(l));
      var r;
      var c;
      var a;
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
            a.onclick = () => this.colorHandler(cmd);
            c.appendChild(a);
            c = a.style;
            c.display = "block";
            c.backgroundColor = "#" + colors[x + y * sq];
            c.height = c.width = "20px";
            c.margin = 0;
          }
        }
        this.colorWindow = colorwin;
        document.querySelector("#page").appendChild(colorwin);
      } else {
        colorwin.style.display = "";
      }
      colorwin.style.top = posy + "px";
      colorwin.style.left = posx + "px";
    }

    hideColors() {
      if (this.colorWindow) {
        this.colorWindow.style.display = "none";
      }
    }

    cmd(a, b, c) {
      a = a.toLowerCase();
      var rng;
      var selection = this.getSelection();
      var bbcode;
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
          if (!b.match(/^(ht|f)tps?:\/\/[\w\.\-\%&\?=\/]+$/)) {
            return alert("Please enter a valid URL.");
          }
          bbcode = "[img]" + b + "[/img]";
          break;
        case "insertorderedlist":
          if (!this.mode) {
            bbcode =
              "[ol]" + selection.replace(/(.+([\r\n]+|$))/gi, "*$1") + "[/ol]";
          }
          break;
        case "insertunorderedlist":
          if (!this.mode) {
            bbcode =
              "[ul]" + selection.replace(/(.+([\r\n]+|$))/gi, "*$1") + "[/ul]";
          }
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
          if (Browser.firefox || Browser.safari) a = "hilitecolor";
          // a="inserthtml";b='<span style="background:'+b+'">'+selection+'</span>'
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
      if (this.mode) {
        if (a == "inserthtml" && Browser.ie) {
          rng = this.doc.selection.createRange();
          if (!rng.text.length) this.doc.body.innerHTML += b;
          else {
            rng.pasteHTML(b);
            rng.collapse(false);
            rng.select();
          }
        } else {
          this.doc.execCommand(a, false, b || false);
          if (this.iframe.contentWindow.focus) {
            this.iframe.contentWindow.focus();
          }
        }
      } else editor.setSelection(this.textarea, bbcode);
    }

    getSelection() {
      if (this.mode) {
        return Browser.ie
          ? this.doc.selection.createRange().text
          : this.window.getSelection();
      } else {
        if (Browser.ie) {
          this.textarea.focus();
          return document.selection.createRange().text;
        } else {
          return this.textarea.value.substring(
            this.textarea.selectionStart,
            this.textarea.selectionEnd
          );
        }
      }
    };

    getSource() {
      return this.doc.body.innerHTML;
    }

    setSource(a) {
      if (this.doc && this.doc.body) this.doc.body.innerHTML = a;
    }

    BBtoHTML(a) {
      a = a
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/(\s) /g, "$1&nbsp;");
      a = this.replaceAll(a, /\[(b|i|u|s)\]([\w\W]*?)\[\/\1\]/gi, "<$1>$2</$1>");
      a = this.replaceAll(a, /\[img\]([^'"\[]+)\[\/img\]/gi, '<img src="$1">');
      a = this.replaceAll(
        a,
        /\[color=([^\]]+)\](.*?)\[\/color\]/gi,
        '<span style="color:$1">$2</span>'
      );
      a = this.replaceAll(
        a,
        /\[size=([^\]]+)\](.*?)\[\/size\]/gi,
        '<span style="font-size:$1">$2</span>'
      );
      a = this.replaceAll(
        a,
        /\[url=([^\]]+)\](.*?)\[\/url\]/gi,
        '<a href="$1">$2</a>'
      );
      a = this.replaceAll(
        a,
        /\[bgcolor=([^\]]+)\](.*?)\[\/bgcolor\]/gi,
        '<span style="backgroun-color:$1">$2</span>'
      );
      a = this.replaceAll(a, /\[h(\d)\](.*?)\[\/h\1\]/, "<h$1>$2</h$1>");
      a = this.replaceAll(
        a,
        /\[align=(left|right|center)\](.*?)\[\/align\]/,
        '<span style="text-align:$1">$2</span>'
      );
      a = this.replaceAll(a, /\[(ul|ol)\]([\w\W]*?)\[\/\1\]/gi, function(s) {
        var tag = RegExp.$1;
        var lis = "";
        var list = RegExp.$2.split(/([\r\n]+|^)\*/);
        var x;
        for (x = 0; x < list.length; x++) {
          if (list[x].match(/\S/)) lis += "<li>" + list[x] + "</li>";
        }
        return "<" + tag + ">" + lis + "</" + tag + ">";
      });
      a = this.replaceAll(a, /\n/g, "<br />");
      return a;
    };

    replaceAll(a, b, c) {
      var tmp = a;
      do {
        a = tmp;
        tmp = a.replace(b, c);
      } while (a != tmp);
      return tmp;
    };

    HTMLtoBB(a) {
      a = a.replace(/[\r\n]+/g, "");
      a = a.replace(/<(hr|br|meta)[^>]*>/gi, "\n");
      a = a.replace(/<img.*?src=["']?([^'"]+)["'][^>]*\/?>/g, "[img]$1[/img]");
      a = this.replaceAll(a, /<(\w+)([^>]*)>([\w\W]*?)<\/\1>/gi, function(
        whole,
        tag,
        attributes,
        innerhtml
      ) {
        var att = {};
        var style = "";
        attributes.replace(
          /(color|size|style|href|src)=(['"]?)(.*?)\2/gi,
          function(whole, attr, q, value) {
            att[attr] = value;
          }
        );

        if (att.style) style = att.style;

        tag = tag.toLowerCase();
        if (tag == "script" || tag == "style" || tag == "hr") return;
        if (style.match(/background(\-color)?:[^;]+(rgb\([^\)]+\)|#\s+)/i)) {
          innerhtml =
            "[bgcolor=#" +
            new Color(RegExp.$2).toHex() +
            "]" +
            innerhtml +
            "[/bgcolor]";
        }
        if (style.match(/text\-align: ?(right|center|left);/i)) {
          innerhtml = "[align=" + RegExp.$1 + "]" + innerhtml + "[/align]";
        }
        if (
          style.match(/font\-style: ?italic;/i) ||
          tag == "i" ||
          tag == "em"
        ) {
          innerhtml = "[I]" + innerhtml + "[/I]";
        }
        if (style.match(/text\-decoration:[^;]*underline;/i) || tag == "u") {
          innerhtml = "[U]" + innerhtml + "[/U]";
        }
        if (
          style.match(/text\-decoration:[^;]*line\-through;/i) ||
          tag == "s"
        ) {
          innerhtml = "[S]" + innerhtml + "[/S]";
        }
        if (
          style.match(/font\-weight: ?bold;/i) ||
          tag == "strong" ||
          tag == "b"
        ) {
          innerhtml = "[B]" + innerhtml + "[/B]";
        }
        if (att.size || style.match(/font\-size: ?([^;]+)/i)) {
          innerhtml =
            "[size=" + (att.size || RegExp.$1) + "]" + innerhtml + "[/size]";
        }
        if (att.color || style.match(/color: ?([^;]+)/i)) {
          innerhtml =
            "[color=" + (att.color || RegExp.$1) + "]" + innerhtml + "[/color]";
        }
        if (tag == "a" && att.href) {
          innerhtml = "[url=" + att.href + "]" + innerhtml + "[/url]";
        }
        if (tag == "ol") innerhtml = "[ol]" + innerhtml + "[/ol]";
        if (tag == "ul") innerhtml = "[ul]" + innerhtml + "[/ul]";
        if (tag.match(/h\d/i)) {
          innerhtml =
            "[" +
            tag.toLowerCase() +
            "]" +
            innerhtml +
            "[/" +
            tag.toLowerCase() +
            "]";
        }
        if (tag == "li") {
          innerhtml = "*" + innerhtml.replace(/[\n\r]+/, "") + "\n";
        }
        if (tag == "p") {
          innerhtml = "\n" + (innerhtml == "&nbsp" ? "" : innerhtml) + "\n";
        }
        if (tag == "div") innerhtml = "\n" + innerhtml;
        return innerhtml;
      });
      return a
        .replace(/&gt;/g, ">")
        .replace(/&amp;/g, "&")
        .replace(/&lt;/g, "<")
        .replace(/&nbsp;/g, " ");
    };

    switchMode(toggle) {
      var t = this.textarea;
      var f = this.iframe;
      if (!toggle) {
        t.value = this.HTMLtoBB(this.getSource());
        t.style.display = "";
        f.style.display = "none";
      } else {
        this.setSource(this.BBtoHTML(t.value));
        t.style.display = "none";
        f.style.display = "";
      }
      this.mode = toggle;
    }

    submit() {
      if (this.mode) {
        this.switchMode(0);
        this.switchMode(1);
      }
    }
  }
  Editor.setSelection = function(t, stuff) {
    var scroll = t.scrollTop;
    if (Browser.ie) {
      t.focus();
      document.selection.createRange().text = stuff;
    } else {
      var s = t.selectionStart;
      var e = t.selectionEnd;
      t.value = t.value.substring(0, s) + stuff + t.value.substr(e);
      t.selectionStart = s + stuff.length;
      t.selectionEnd = s + stuff.length;
    }
    t.focus();
    t.scrollTop = scroll;
  };

  // TODO: Create an instance for this state
  // instead of abusing the module

  let flashInterval;
  let originalTitle = '';
  let lastTitle = '';

  const flashTitle = function(title) {
    if (document.hasFocus()) {
      return;
    }
    stopTitleFlashing();
    if (originalTitle == "") {
      originalTitle = document.title;
    }
    lastTitle = title;
    flashInterval = setInterval(function() {
      document.title =
        document.title == originalTitle
          ? lastTitle
          : originalTitle;
    }, 1000);
  };

  const stopTitleFlashing = function() {
    if (originalTitle) {
      document.title = originalTitle;
    }
    originalTitle = '';
    clearInterval(flashInterval);
  };

  const imageResizer = function(imgs) {
    var img;
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

  const makeResizer = function(iw, nw, ih, nh, img) {
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
  function scrollablepagelist(pl) {
    if (pl.addEventListener) {
      pl.addEventListener("DOMMouseScroll", scrollpagelist, false);
    }
    pl.onmousewheel = scrollpagelist;
  }

  /**
   * Swaps two elements in an array
   * @param  {Array} array
   * @param  {Number} fromIndex
   * @param  {Number} toIndex
   * @return {Array}
   */
  function swap(array, fromIndex, toIndex) {
    var cache = array[fromIndex];
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

      for (var x = 0; x < elements.length; x++) {
        me.apply(elements[x], typeof b.handle == "function" ? b.handle(a[x]) : null);
      }
    }

    ondrop(element) {
      if (me.change) me.coords = [];
      me.change = 0;
      var s = element.el.style;
      s.top = s.left = 0;
      if (typeof me.onend == "function") me.onend(element);
    }

    ondrag(a) {
      var x;
      var d = me.elems;
      var dl = d.length;
      var c;
      var cel = getCoordinates(a.el);
      var c2;
      var ch = false;
      var ov = me.options.vertical || 0;
      var oh = me.options.horizontal || 0;
      var index;
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
          ch === false &&
          (ov ? a.my < c.yh && a.dy < 0 : a.mx < c.xw && a.my < c.yh)
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

  function sortableTree(tree, prefix, formfield) {
    var tmp = tree.querySelectorAll("li");
    var x;
    var items = [];
    var seperators = [];
    var drag;
    for (x = 0; x < tmp.length; x++) {
      if (tmp[x].className != "title") items.push(tmp[x]);
    }

    function parsetree(tree) {
      var nodes = tree.getElementsByTagName("li");
      var order = {};
      var node;
      var sub;
      var gotsomethin = 0;
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
      insertBefore(tmp, items[x]);
    }

    drag = new Drag$1().noChildActivation();
    drag.drops(seperators.concat(items)).addListener({
      ondragover: function(a) {
        a.droptarget.style.border = "1px solid #000";
      },
      ondragout: function(a) {
        a.droptarget.style.border = "none";
      },
      ondrop: function(a) {
        var next = a.droptarget.nextSibling;
        var tmp;
        var parentlock = a.el.className == "parentlock";
        var nofirstlevel = a.el.className == "nofirstlevel";
        if (a.droptarget) a.droptarget.style.border = "none";
        if (a.droptarget.className == "seperator") {
          if (parentlock && a.droptarget.parentNode != a.el.parentNode) {
            return drag.reset(a.el, 1);
          }
          if (nofirstlevel && a.droptarget.parentNode.className == "tree") {
            return drag.reset(a.el, 1);
          }
          if (isChildOf(a.droptarget, a.el) || a.el == next) {
            return drag.reset(a.el, 1);
          }
          if (next.className == "spacer") {
            next.parentNode.removeChild(next);
          }
          if (next.className != "spacer") {
            insertAfter(a.el.previousSibling, a.droptarget);
          } else {
            a.el.previousSibling.parentNode.removeChild(a.el.previousSibling);
          }
          insertAfter(a.el, a.droptarget);
        } else if (!parentlock && a.droptarget.tagName == "LI") {
          tmp = a.droptarget.getElementsByTagName("ul")[0];
          if (!tmp) {
            tmp = document.createElement("ul");
            a.droptarget.appendChild(tmp);
          }
          tmp.appendChild(a.el.previousSibling);
          tmp.appendChild(a.el);
          a.droptarget.appendChild(tmp);
        }
        drag.reset(a.el, 1);
        if (formfield) {
          formfield.value = JSON.stringify(parsetree(tree));
        }
      }
    });

    for (x = 0; x < items.length; x++) {
      drag.apply(items[x]);
    }
  }

  function SWF(url, name, settings) {
    var object;
    var embed;
    var x;
    var s = {
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
    for (x in s) {
      if (x != "width" && x != "height") {
        object += '<param name="' + x + '" value="' + s[x] + '"></param>';
        embed += " " + x + '="' + s[x] + '"';
      }
    }
    embed += "></embed>";
    object += "</object>";
    var tmp = document.createElement("span");
    tmp.innerHTML = Browser.ie ? object : embed;
    return tmp.getElementsByTagName("*")[0];
  }

  class Window {
    constructor() {
      assign(this, {
        title: "Title",
        wait: true,
        content: "Content",
        open: false,
        useoverlay: false,
        minimizable: true,
        resize: false,
        className: "",
        pos: "center",
        zIndex: getHighestZIndex()
      });
    }

    create() {
      if (this.windowContainer) {
        // DOM already created
        return;
      }
      var windowContainer = document.createElement("div");
      var titleBar = document.createElement("div");
      var contentContainer = document.createElement("div");
      var windowControls = document.createElement("div");
      var minimizeButton = document.createElement("div");
      var closeButton = document.createElement("div");
      var pos = this.pos;
      var s;

      this.windowContainer = windowContainer;
      if (this.id) {
        windowContainer.id = this.id;
      }
      this.contentcontainer = contentContainer;

      if (this.useOverlay) {
        toggleOverlay(true, this.zIndex);
      }
      windowContainer.className = "window" + (this.className ? " " + this.className : "");
      titleBar.className = "title";
      contentContainer.className = "content";
      if (this.minimizable) {
        minimizeButton.innerHTML = "-";
        minimizeButton.onclick = () => this.minimize();
      }
      closeButton.innerHTML = "X";
      closeButton.onclick = () => me.close();
      windowControls.appendChild(minimizeButton);
      windowControls.appendChild(closeButton);
      windowControls.className = "controls";
      titleBar.innerHTML = me.title;
      contentContainer.innerHTML = me.content;
      titleBar.appendChild(windowControls);
      windowContainer.appendChild(titleBar);
      windowContainer.appendChild(contentContainer);
      document.body.appendChild(windowContainer);

      if (me.resize) {
        var targ = windowContainer.querySelector(me.resize);
        if (!targ) return alert("Resize target not found");
        targ.style.width = targ.clientWidth + "px";
        targ.style.height = targ.clientHeight + "px";
        var rsize = document.createElement("div");
        rsize.className = "resize";
        windowContainer.appendChild(rsize);
        rsize.style.left = windowContainer.clientWidth - 16 + "px";
        rsize.style.top = windowContainer.clientHeight - 16 + "px";
        new Drag$1()
          .boundingBox(100, 100, Infinity, Infinity)
          .addListener({
            ondrag: function(a) {
              var w = parseFloat(targ.style.width) + a.dx;
              var h = parseFloat(targ.style.height) + a.dy;
              targ.style.width = w + "px";
              if (w < windowContainer.clientWidth - 20) {
                targ.style.width = windowContainer.clientWidth + "px";
              } else {
                rsize.style.left = windowContainer.clientWidth - 16 + "px";
              }
              targ.style.height = h + "px";
            },
            ondrop: function(a) {
              rsize.style.left = windowContainer.clientWidth - 16 + "px";
            }
          })
          .apply(rsize);
        targ.style.width = windowContainer.clientWidth + "px";
        rsize.style.left = windowContainer.clientWidth - 16 + "px";
      }

      s = windowContainer.style;
      s.zIndex = this.zIndex + 5;

      if (me.wait) {
        JAX.onImagesLoaded(
          windowContainer.getElementsByTagName("img"),
          function() {
            me.setPosition(pos);
          },
          2000
        );
      } else me.setPosition(pos);

      me.drag = new Drag$1()
        .autoZ()
        .noChildActivation()
        .boundingBox(0, 0, document.documentElement.clientWidth - 50, document.documentElement.clientHeight - 50)
        .apply(windowContainer, titleBar);
      windowContainer.close = me.close;
      windowContainer.minimize = this.minimize;
      return windowContainer;
    }

    close() {
      if (!me.open) return;
      var s = me.open.style;
      if (me.animate && false) {
        // this is broken until further notice
        new Animation(me.open, 10)
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
      if (this.useOverlay) toggleOverlay(false);
    }

    minimize() {
      var c = me.open;
      var x;
      var w = 0;
      var isMinimized = c.classList.contains("minimized");
      c.classList.toggle("minimized");
      if (isMinimized) {
        c.removeAttribute("draggable");
        me.setPosition(me.oldpos, 0);
      } else {
        c.setAttribute("draggable", "false");
        var wins = document.querySelectorAll(".window");
        for (x = 0; x < wins.length; x++) {
          if (wins[x].classList.contains("minimized")) {
            w += parseInt(wins[x].clientWidth);
          }
        }
        me.oldpos = me.getPosition();
        me.setPosition("bl " + w + " 0", 0);
      }
    }

    setPosition(pos, animate) {
      var d1 = me.open;
      var x = 0;
      var y = 0;
      var cH = document.documentElement.clientHeight;
      var cW = document.documentElement.clientWidth;
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
        new Animation(d1, 10)
          .add("top", y - 100 + "px", y + "px")
          .play();
      } else d1.style.top = y + "px";
      me.pos = pos;
    }

    getPosition() {
      var s = this.windowContainer.style;
      return "tl " + parseFloat(s.left) + " " + parseFloat(s.top);
    }
  }
  Window.close = function(win) {
    do {
      if (win.close) {
        win.close();
        break;
      }
      win = win.offsetParent;
    } while (win);
  };

  var JAX$1 = {
    ajax,
    browser: Browser,
    color: Color,
    date,
    datepicker: DatePicker,
    drag: Drag$1,
    editor: Editor,
    el,
    event: Event,
    flashTitle,
    imageResizer,
    makeResizer,
    scrollablepagelist,
    smalldate,
    sortable: Sortable,
    sortableTree,
    stopTitleFlashing,
    sfx: Animation,
    SWF,
    window: Window,

    // TODO: organize
    assign,
    gracefulDegrade,
    convertSwitches,
    checkAll,
    onImagesLoaded,
    handleTabs,
    toggle,
    collapse,
    overlay: toggleOverlay,
    scrollTo
  };

  class Uploader {
    constructor() {
      this.uploaders = [];
    }

    listenerHandler(id, action, args) {
      // moving arguments around
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
      if (this.uploaders[id] && this.uploaders[id][action]) {
        this.uploaders[id][action].apply(this.uploaders[id], args);
      }
    }

    createButton () {
      var d = document.createElement("div");
      d.className = "uploadbutton";
      d.innerHTML = "Add File(s)";
      return [d, this.create(d)];
    }

    create(el, w, h, url) {
      var nid = this.uploaders.length;
      var swf = JAX.SWF("Script/uploader.swf", "uploader" + nid, {
        width: w || "100%",
        height: h || "100%",
        allowScriptAccess: "sameDomain",
        wmode: "transparent",
        flashvars: "id=" + nid
      });

      var s = swf.style;
      s.position = "absolute";
      s.left = "0px";
      s.top = "0px";
      el.style.position = "relative";
      el.appendChild(swf);
      this.uploaders.push([]);
      this.uploaders[nid].flashObj = swf;
      this.uploaders[nid].id = nid;
      return this.uploaders[nid];
    }

    upload(nid, fileobj, url) {
      this.uploaders[nid].flashObj.upload(fileobj.id, url);
    }
  }

  // Uploader is a singleton
  var Uploader$1 = new Uploader();

  class Sound {
    constructor() {
      this._soundCache = {};
    }
    load(title, file, autoplay) {
      var audio = new Audio();
      this._soundCache[title] = audio;
      audio.autoplay = !!autoplay;
      audio.src = file;
    }
    play(title) {
      this._soundCache[title].play();
    }
    loadAndPlay(title, file) {
      load(title, file, true);
    }
  }
  // Sound is a singleton
  var Sound$1 = new Sound();

  var index = {
    JAX: JAX$1,
    Uploader: Uploader$1,
    Sound: Sound$1
  };

  return index;

}());

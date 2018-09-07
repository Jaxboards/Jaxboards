var RUN = (function () {
  'use strict';

  function getComputedStyle(a, b) {
    if (!a) return false;
    if (a.currentStyle) return a.currentStyle;
    if (window.getComputedStyle) return window.getComputedStyle(a, b);
    return false;
  }

  function getCoordinates(a) {
    let x = 0;
    let y = 0;
    const h = parseInt(a.offsetHeight, 10) || 0;
    const w = parseInt(a.offsetWidth, 10) || 0;
    let element = a;
    do {
      x += parseInt(element.offsetLeft, 10) || 0;
      y += parseInt(element.offsetTop, 10) || 0;
      element = element.offsetParent;
    } while (element);
    return {
      x,
      y,
      yh: y + h,
      xw: x + w,
      w,
      h,
    };
  }

  function isChildOf(a, b) {
    return b.contains(a);
  }

  function insertBefore(a, b) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode.insertBefore(a, b);
  }

  function insertAfter(a, b) {
    if (a.parentNode) a.parentNode.removeChild(a);
    b.parentNode.insertBefore(a, b.nextSibling);
  }

  function replace(a, b) {
    insertBefore(b, a);
    if (a.parentNode) a.parentNode.removeChild(a);
  }

  function getHighestZIndex() {
    const allElements = Array.from(document.getElementsByTagName('*'));
    const max = allElements.reduce((maxZ, element) => {
      if (element.style.zIndex && Number(element.style.zIndex) > maxZ) {
        return Number(element.style.zIndex);
      }
      return maxZ;
    }, 0);
    return max + 1;
  }

  class Color {
    constructor(colorToParse) {
      let a = colorToParse;
      // RGB
      if (typeof a === 'object') this.rgb = a;
      else if (typeof a === 'string') {
        const rgbMatch = a.match(/^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i);
        const hexMatch = a.match(/#?[^\da-fA-F]/);
        if (rgbMatch) {
          rgbMatch[1] = parseFloat(rgbMatch[1]);
          rgbMatch[2] = parseFloat(rgbMatch[2]);
          rgbMatch[3] = parseFloat(rgbMatch[3]);
          rgbMatch.shift();
          this.rgb = rgbMatch;
        } else if (hexMatch) {
          if (a.charAt(0) === '#') {
            a = a.substr(1);
          }
          if (a.length === 3) {
            a = a.charAt(0)
              + a.charAt(0)
              + a.charAt(1)
              + a.charAt(1)
              + a.charAt(2)
              + a.charAt(2);
          }
          if (a.length !== 6) this.rgb = [0, 0, 0];
          else {
            this.rgb = [];
            for (let x = 0; x < 3; x += 1) {
              this.rgb[x] = parseInt(a.substr(x * 2, 2), 16);
            }
          }
        }
      } else {
        this.rgb = [0, 0, 0];
      }
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
      let tmp2;
      let tmp = '';
      let x;
      const hex = '0123456789ABCDEF';
      for (x = 0; x < 3; x += 1) {
        tmp2 = this.rgb[x];
        tmp
          += hex.charAt(Math.floor(tmp2 / 16)) + hex.charAt(Math.floor(tmp2 % 16));
      }
      return tmp;
    }
  }

  class Animation {
    constructor(el$$1, steps, delay, loop) {
      this.el = el$$1;
      this.steps = steps || 30;
      this.delay = delay || 20;
      this.curLineup = 0;
      this.stepCount = 0;
      this.loop = loop || 0;
      this.lineup = [[]];
    }

    play() {
      this.interval = setInterval(() => this.step(), this.delay);
      return this;
    }

    morph(from, percent, to) {
      if (Array.isArray(from) && from.length === to.length) {
        return from
          .map((value, i) => Math.round(this.morph(value, percent, to[i])));
      }
      return (to - from) * percent + from;
    }

    step() {
      const curL = this.lineup[this.curLineup];
      this.stepCount += 1;
      let sc = this.stepCount;
      if (typeof curL[0] === 'function') {
        curL[0](this.el);
        sc = this.steps;
      } else {
        curL.forEach((keyFrame) => {
          let toValue = this.morph(keyFrame[1], sc / this.steps, keyFrame[2]);
          if (keyFrame[0].match(/color/i)) {
            toValue = `#${(new Color(toValue)).toHex()}`;
          } else if (keyFrame[0] !== 'opacity') toValue = Math.round(toValue);
          this.el.style[keyFrame[0]] = keyFrame[3] + toValue + keyFrame[4];
        });
      }
      if (sc === this.steps) {
        if (this.lineup.length - 1 > this.curLineup) {
          this.stepCount = 0;
          this.curLineup += 1;
        } else if (this.loop === 1) {
          this.stepCount = 0;
          this.curLineup = 0;
        } else clearInterval(this.interval);
      }
    }

    add(what, from, to) {
      let t = ['', '', ''];
      let fromParsed;
      if (what.match(/color/i)) {
        fromParsed = (new Color(from)).toRGB();
        t[1] = (new Color(to)).toRGB();
      } else {
        t = to.match(/(\D*)(-?\d+)(\D*)/);
        t.shift();
        fromParsed = parseFloat(from.match(/-?\d+/));
      }
      this.lineup[this.lineup.length - 1].push([what, fromParsed, t[1], t[0], t[2]]);
      return this;
    }

    dehighlight() {
      this.el.style.backgroundColor = '';
      const bg = getComputedStyle(this.el).backgroundColor.toString();
      let bg2;
      this.el.classList.add('highlight');
      bg2 = getComputedStyle(this.el).backgroundColor.toString();
      if (bg2 === bg) bg2 = 'FF0';
      this.el.classList.add('highlight');
      return this.add('backgroundColor', bg2, bg)
        .then(() => {
          this.el.style.backgroundColor = bg;
        });
    }

    then(what, from, to, steps) {
      this.lineup.push([]);
      if (steps) this.steps = steps;
      if (typeof what === 'function') {
        this.lineup[this.lineup.length - 1].push(what);
      } else {
        this.add(what, from, to);
      }
      return this;
    }
  }

  const { userAgent } = navigator;

  var Browser = {
    chrome: !!userAgent.match(/chrome/i),
    ie: !!userAgent.match(/msie/i),
    iphone: !!userAgent.match(/iphone/i),
    mobile: !!userAgent.match(/mobile/i),
    n3ds: !!userAgent.match(/nintendo 3ds/),
    firefox: !!userAgent.match(/firefox/i),
    safari: !!userAgent.match(/safari/i),
  };

  const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];

  const daysshort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; // I don't think I'll need a dayslong ever

  class DatePicker {
    constructor(el$$1) {
      let dp = document.querySelector('#datepicker');
      let s;
      const c = getCoordinates(el$$1);
      if (!dp) {
        dp = document.createElement('table');
        dp.id = 'datepicker';
        document.querySelector('#page').appendChild(dp);
      }
      s = dp.style;
      s.display = 'table';
      s.zIndex = getHighestZIndex();
      s.top = `${c.yh}px`;
      s.left = `${c.x}px`;
      s = el$$1.value.split('/');
      if (s.length === 3) {
        this.selectedDate = [
          parseInt(s[2], 10),
          parseInt(s[0], 10) - 1,
          parseInt(s[1], 10),
        ];
      } else this.selectedDate = undefined;

      this.el = el$$1;
      this.generate(s[2], s[0] ? parseInt(s[0], 10) - 1 : undefined, s[1]);
    }

    // month should be 0 for jan, 11 for dec
    generate(iyear, imonth, iday) {
      let date = new Date();
      const dp = document.querySelector('#datepicker');
      let row;
      let cell;
      let [year, month, day] = [iyear, imonth, iday];
      // date here is today
      if (year === undefined) {
        year = date.getFullYear();
        month = date.getMonth();
        day = date.getDate();
        this.selectedDate = [year, month, day];
      }

      if (month === -1) {
        year -= 1;
        month = 11;
      }
      if (month === 12) {
        year += 1;
        month = 0;
      }

      this.lastDate = [year, month, day];

      // this date is used to calculate days in month and the day the first is on
      const numdaysinmonth = new Date(year, month + 1, 0).getDate();
      const first = new Date(year, month, 1).getDay();

      date = new Date(year, month, day);
      // generate the table now
      dp.innerHTML = ''; // clear

      // year
      row = dp.insertRow(0);
      cell = row.insertCell(0);
      cell.innerHTML = '<';
      cell.className = 'control';
      cell.onclick = () => this.lastYear();

      cell = row.insertCell(1);
      cell.colSpan = '5';
      cell.className = 'year';
      cell.innerHTML = year;
      cell = row.insertCell(2);
      cell.innerHTML = '>';
      cell.className = 'control';
      cell.onclick = () => this.nextYear();

      // month title
      row = dp.insertRow(1);
      cell = row.insertCell(0);
      cell.innerHTML = '<';
      cell.className = 'control';
      cell.onclick = () => this.lastMonth();

      cell = row.insertCell(1);
      cell.colSpan = '5';
      cell.innerHTML = months[month];
      cell.className = 'month';
      cell = row.insertCell(2);
      cell.innerHTML = '>';
      cell.className = 'control';
      cell.onclick = () => this.nextMonth();

      // weekdays
      row = dp.insertRow(2);
      row.className = 'weekdays';
      for (let x = 0; x < 7; x += 1) {
        row.insertCell(x).innerHTML = daysshort[x];
      }

      row = dp.insertRow(3);
      // generate numbers
      for (let x = 0; x < numdaysinmonth; x += 1) {
        if (!x) {
          for (let i = 0; i < first; i += 1) {
            row.insertCell(i);
          }
        }
        if ((first + x) % 7 === 0) {
          row = dp.insertRow(dp.rows.length);
        }
        cell = row.insertCell((first + x) % 7);
        cell.onclick = () => this.insert(this);

        cell.className = `day${
        year === this.selectedDate[0]
        && month === this.selectedDate[1]
        && x + 1 === this.selectedDate[2]
          ? ' selected'
          : ''}`;
        cell.innerHTML = x + 1;
      }
    }

    lastYear() {
      const l = this.lastDate;
      this.generate(l[0] - 1, l[1], l[2]);
    }

    nextYear() {
      const l = this.lastDate;
      this.generate(l[0] + 1, l[1], l[2]);
    }

    lastMonth() {
      const l = this.lastDate;
      this.generate(l[0], l[1] - 1, l[2]);
    }

    nextMonth() {
      const l = this.lastDate;
      this.generate(l[0], l[1] + 1, l[2]);
    }

    insert(cell) {
      const l = this.lastDate;
      this.el.value = `${l[1] + 1}/${cell.innerHTML}/${l[0]}`;
      this.hide();
    }
  }

  // Static methods
  DatePicker.init = el$$1 => new DatePicker(el$$1);
  DatePicker.hide = () => {
    document.querySelector('#datepicker').style.display = 'none';
  };

  /**
   * This method adds some decoration to the default browser event.
   * This can probably be replaced with something more modern.
   */
  function Event$1(e) {
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
      default:
        break;
    }
    if (typeof e.srcElement === 'undefined') e.srcElement = e.target;
    if (typeof e.pageY === 'undefined') {
      e.pageY = e.clientY + (parseInt(dE.scrollTop || dB.scrollTop, 10) || 0);
      e.pageX = e.clientX + (parseInt(dE.scrollLeft || dB.scrollLeft, 10) || 0);
    }
    e.cancel = () => {
      e.returnValue = false;
      if (e.preventDefault) e.preventDefault();
      return e;
    };
    e.stopBubbling = () => {
      if (e.stopPropagation) e.stopPropagation();
      e.cancelBubble = true;
      return e;
    };
    return e;
  }

  // TODO: There are places in the source that are using this to store a callback
  // Refactor this
  Event$1.onPageChange = function onPageChange() {};

  // scrolling page list functionality
  function scrollpagelist(event) {
    const e = Event$1(event).cancel();
    const wheelDelta = e.detail || e.wheelDelta;
    let delta = Math.abs(wheelDelta) / wheelDelta;
    if (Browser.chrome) {
      delta *= -1;
    }
    const p = Array.from(this.querySelectorAll('a'));
    const startPage = parseInt(p[1].innerHTML, 10);
    const lastPage = parseInt(p[p.length - 1].innerHTML, 10);
    const between = p.length - 2;
    if (Browser.ie) {
      delta *= -1;
    }
    if ((delta > 0 && startPage + between < lastPage) || (delta < 0 && startPage > 2)) {
      for (let x = 0; x < between; x += 1) {
        p[x + 1].href = p[x + 1].href.replace(/\d+$/, x + startPage + delta);
        p[x + 1].innerHTML = startPage + x + delta;
      }
    }
  }
  function scrollablepagelist (pl) {
    if (pl.addEventListener) {
      pl.addEventListener('DOMMouseScroll', scrollpagelist, false);
    }
    pl.onmousewheel = scrollpagelist;
  }

  const maxDimension = '999999px';

  function makeResizer(iw, nw, ih, nh, img) {
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
      const e = Event$1(event);
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


  function imageResizer(imgs) {
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

  function makeImageGallery (gallery) {
    if (gallery.madeGallery) {
      return;
    }
    gallery.madeGallery = true;
    const controls = document.createElement('div');
    const next = document.createElement('a');
    const prev = document.createElement('a');
    const status = {
      index: 0,
      max: Math.max(gallery.querySelectorAll('img').length, 1),
      showNext() {
        if (this.index < this.max - 1) {
          this.index += 1;
        }
        this.update();
      },
      showPrev() {
        if (this.index > 0) {
          this.index -= 1;
        }
        this.update();
      },
      update() {
        const imgs = gallery.querySelectorAll('img');
        imgs.forEach((img, i) => {
          let container;
          if (img.madeResized) {
            container = img.parentNode;
          } else {
            container = img;
          }
          container.style.display = i !== this.index ? 'none' : 'block';
        });
      },
    };
    next.innerHTML = 'Next &raquo;';
    next.href = '#';
    next.onclick = () => {
      status.showNext();
      return false;
    };

    prev.innerHTML = 'Prev &laquo;';
    prev.href = '#';
    prev.onclick = () => {
      status.showPrev();
      return false;
    };

    status.update();
    controls.appendChild(prev);
    controls.appendChild(document.createTextNode(' '));
    controls.appendChild(next);
    gallery.appendChild(controls);
  }

  function ordsuffix(a) {
    return (
      a
      + (Math.round(a / 10) === 1 ? 'th' : ['', 'st', 'nd', 'rd'][a % 10] || 'th')
    );
  }

  function date(a) {
    const old = new Date();
    const now = new Date();
    let fmt;
    const yday = new Date();
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    yday.setTime(yday - 1000 * 60 * 60 * 24);
    old.setTime(a * 1000); // setTime uses milliseconds, we'll be using UNIX Times as the argument
    const hours = `${old.getHours() % 12 || 12}`;
    const ampm = hours >= 12 ? 'pm' : 'am';
    const mins = `${old.getMinutes()}`.padStart(2, '0');
    const dstr = `${old.getDate()} ${old.getMonth()} ${old.getFullYear()}`;
    const delta = (now.getTime() - old.getTime()) / 1000;
    if (delta < 90) {
      fmt = 'a minute ago';
    } else if (delta < 3600) {
      fmt = `${Math.round(delta / 60)} minutes ago`;
    } else if (
      `${now.getDate()} ${now.getMonth()} ${now.getFullYear()}`
      === dstr
    ) {
      fmt = `Today @ ${hours}:${mins} ${ampm}`;
    } else if (
      `${yday.getDate()} ${yday.getMonth()} ${yday.getFullYear()}`
      === dstr
    ) {
      fmt = `Yesterday @ ${hours}:${mins} ${ampm}`;
    } else {
      fmt = `${months[old.getMonth()]} ${ordsuffix(old.getDate())}, ${old.getFullYear()} @ ${hours}:${mins} ${ampm}`;
    }
    return fmt;
  }

  function smalldate(a) {
    const d = new Date();
    d.setTime(a * 1000);
    let hours = d.getHours();
    const ampm = hours >= 12 ? 'pm' : 'am';
    hours %= 12;
    hours = hours || 12;
    const minutes = `${d.getMinutes()}`.padStart(2, '0');
    const month = d.getMonth() + 1;
    const day = `${d.getDate()}`.padStart(2, '0');
    const year = d.getFullYear();
    return `${hours}:${minutes}${ampm}, ${month}/${day}/${year}`;
  }

  /* global RUN */

  // This file is just a dumping ground until I can find better homes for these

  function assign(a, b) {
    Object.assign(a, b);
  }

  /**
   * Tries to call a function, if it exists.
   * @param  {Function} method
   * @param  {...any} args
   * @return {any}
   */
  function tryInvoke(method, ...args) {
    if (method && typeof method === 'function') {
      return method(...args);
    }
    return null;
  }

  function convertSwitches(switches) {
    switches.forEach((switchElement) => {
      const div = document.createElement('div');
      div.className = switchElement.className.replace('switch', 'switch_converted');
      switchElement.style.display = 'none';
      if (!switchElement.checked) {
        div.style.backgroundPosition = 'bottom';
      }
      div.onclick = () => {
        switchElement.checked = !switchElement.checked;
        this.style.backgroundPosition = switchElement.checked ? 'top' : 'bottom';
        tryInvoke(switchElement.onclick);
      };
      insertAfter(div, switchElement);
    });
  }

  function onImagesLoaded(imgs, callback, timeout) {
    const dbj = {
      imgs: [],
      imgsloaded: 1,
      called: false,
      force() {
        if (!dbj.called) callback();
      },
      callback() {
        if (dbj.called) {
          return;
        }
        if (!dbj.imgs.includes(this.src)) {
          return;
        }
        dbj.imgs.splice(dbj.imgs.indexOf(this.src), 1);
        if (dbj.imgs.length === 0) {
          callback();
          dbj.called = true;
        }
      },
    };
    Array.from(imgs).forEach((img) => {
      if (dbj.imgs.includes(img.src) === false && !img.loaded) {
        dbj.imgs.push(img.src);
        img.addEventListener('load', dbj.callback);
        img.src = img.src;
      }
    });
    if (!imgs.length) {
      callback();
      dbj.called = true;
    } else if (timeout) {
      setTimeout(dbj.force, timeout);
    }
  }

  function updateDates() {
    let parsed;
    const dates = Array.from(document.querySelectorAll('.autodate'));
    if (!dates) {
      return;
    }
    dates.forEach((el$$1) => {
      parsed = el$$1.classList.contains('smalldate')
        ? smalldate(parseInt(el$$1.title, 10))
        : date(parseInt(el$$1.title, 10));
      if (parsed !== el$$1.innerHTML) {
        el$$1.innerHTML = parsed;
      }
    });
  }

  function gracefulDegrade(a) {
    if (typeof RUN !== 'undefined') {
      updateDates();
    }
    const links = Array.from(a.querySelectorAll('a'));
    links.forEach((link) => {
      if (link.href) {
        const href = link.getAttribute('href');
        if (href.charAt(0) === '?') {
          const oldclick = link.onclick;
          link.onclick = function onclick() {
            if (!oldclick || oldclick() !== false) {
              RUN.stream.location(href);
            }
            return false;
          };
        } else if (link.getAttribute('href').substr(0, 4) === 'http') {
          link.target = '_BLANK';
        }
      }
    });
    convertSwitches(Array.from(a.querySelectorAll('.switch')));

    const bbcodeimgs = Array.from(document.querySelectorAll('.bbcodeimg'));
    if (bbcodeimgs) {
      onImagesLoaded(
        bbcodeimgs,
        () => {
          // resizer on large images
          imageResizer(bbcodeimgs);

          // handle image galleries
          const galleries = Array.from(document.querySelectorAll('.image_gallery'));
          galleries.map(makeImageGallery);
        },
        2000,
      );
    }

    // Initialize page lists that scroll with scroll wheel
    const pages = Array.from(a.querySelectorAll('.pages'));
    if (pages.length) {
      pages.map(scrollablepagelist);
    }

    // Set up date pickers
    const dateElements = Array.from(a.querySelectorAll('input.date'));
    if (dateElements.length) {
      dateElements.forEach((inputElement) => {
        inputElement.onclick = () => DatePicker.init(this);
        inputElement.onkeydown = () => DatePicker.hide();
      });
    }
  }

  function toggleOverlay(show) {
    const dE = document.documentElement;
    let ol = document.getElementById('overlay');
    let s;
    if (ol) {
      s = ol.style;
      s.zIndex = getHighestZIndex();
      s.top = 0;
      s.height = `${dE.clientHeight}px`;
      s.width = `${dE.clientWidth}px`;
      s.display = show ? '' : 'none';
    } else {
      if (!show) return;
      ol = document.createElement('div');
      s = ol.style;
      ol.id = 'overlay';
      s.height = `${dE.clientHeight}0px`;
      s.width = `${dE.clientWidth}0px`;
      dE.appendChild(ol);
    }
  }

  function scrollTo(pos, el$$1 = Browser.chrome ? document.body : document.documentElement) {
    const screenrel = (
      parseFloat(document.body.clientHeight)
      - parseFloat(document.documentElement.clientHeight)
    );
    const top = parseFloat(el$$1.scrollTop);
    const position = screenrel < pos ? screenrel : pos;
    const diff = position - top;
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
    return me */
  }

  /**
   * Run a callback function either when the DOM is loaded and ready,
   * or immediately if the document is already loaded.
   * @param {Function} callback
   */
  function onDOMReady(callback) {
    if (document.readyState === 'complete') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  }

  function stripHTML(html) {
    return html.valueOf()
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  // TODO: Create an instance for this state
  // instead of abusing the module

  let flashInterval;
  let originalTitle = '';
  let lastTitle = '';

  function stopTitleFlashing() {
    if (originalTitle) {
      document.title = originalTitle;
    }
    originalTitle = '';
    clearInterval(flashInterval);
  }

  function flashTitle(title) {
    if (document.hasFocus()) {
      return;
    }
    stopTitleFlashing();
    if (!originalTitle) {
      originalTitle = document.title;
    }
    lastTitle = title;
    flashInterval = setInterval(() => {
      document.title = document.title === originalTitle
        ? lastTitle
        : originalTitle;
    }, 1000);
  }

  /**
   * For some reason, I designed this method
   * to accept Objects (key/value pairs)
   * or 2 arguments:  keys and values
   * The purpose is to construct data to send over URL or POST data
   *
   * @example
   * buildQueryString({key: 'value', key2: 'value2'}) === 'key=value&key2=value2';
   *
   * @example
   * buildQueryString(['key', 'key2'], ['value, 'value2']) === 'key=value&key2=value2'
   *
   * @return {String}
   */
  function buildQueryString(keys, values) {
    if (!keys) {
      return '';
    }
    if (values) {
      return keys
        .map((key, index) => `${encodeURIComponent(key)}=${encodeURIComponent(values[index] || '')}`)
        .join('&');
    }
    return Object.keys(keys)
      .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(keys[key] || '')}`)
      .join('&');
  }

  class Ajax {
    constructor(s) {
      this.setup = {
        readyState: 4,
        callback() {},
        method: 'POST',
        ...s,
      };
    }

    load(url, callback, data, method = this.setup.method, requestType = 1) {
      // requestType is an enum (1=update, 2=load new)
      let sendData = null;
      if (
        data
        && Array.isArray(data)
        && Array.isArray(data[0])
        && data[0].length === data[1].length
      ) {
        sendData = buildQueryString(data[0], data[1]);
      } else if (typeof data !== 'string') {
        sendData = buildQueryString(data);
      }
      const request = new XMLHttpRequest();
      if (callback) {
        this.setup.callback = callback;
      }
      request.onreadystatechange = () => {
        if (request.readyState === this.setup.readyState) {
          this.setup.callback(request);
        }
      };
      if (!request) return false;
      request.open(method, url, true);
      request.url = url;
      request.type = requestType;
      if (method) {
        request.setRequestHeader(
          'Content-Type',
          'application/x-www-form-urlencoded',
        );
      }
      request.setRequestHeader('X-JSACCESS', requestType);
      request.send(sendData);
      return request;
    }
  }

  function openTooltip (el$$1) {
    let tooltip = document.getElementById('tooltip_thingy');
    const pos = getCoordinates(el$$1);
    const title = stripHTML(el$$1.title);
    // Prevent the browser from showing its own title
    el$$1.title = '';
    if (!title) return;
    if (!tooltip) {
      tooltip = document.createElement('table');
      const t = tooltip.insertRow(0);
      const c = tooltip.insertRow(1);
      const b = tooltip.insertRow(2);
      let a;

      tooltip.id = 'tooltip_thingy';
      tooltip.className = 'tooltip';
      t.className = 'top';
      c.className = 'content';
      b.className = 'bottom';
      a = t.insertCell(0);
      a.className = 'left';
      a.colSpan = 2;
      a = t.insertCell(1);
      a.className = 'right';
      a = c.insertCell(0);
      a.className = 'left';
      a = c.insertCell(1);
      a.innerHTML = 'default text';
      a = c.insertCell(2);
      a.className = 'right';
      a = b.insertCell(0);
      a.className = 'left';
      a.colSpan = 2;
      a = b.insertCell(1);
      a.className = 'right';
      document.querySelector('#page').appendChild(tooltip);
    }

    tooltip.rows[1].cells[1].innerHTML = title;
    tooltip.style.display = '';
    tooltip.style.top = `${pos.y - tooltip.clientHeight}px`;
    tooltip.style.left = `${pos.x}px`;
    tooltip.style.zIndex = getHighestZIndex();
    el$$1.onmouseout = () => {
      el$$1.title = title;
      document.querySelector('#tooltip_thingy').style.display = 'none';
    };
  }

  class Drag {
    constructor() {
      this.droppables = [];
    }

    start(event, t, handle) {
      const e = new Event$1(event).cancel().stopBubbling();
      const el$$1 = t || event.target;
      const s = getComputedStyle(el$$1);
      const highz = getHighestZIndex();
      if (this.noChild && (e.srcElement || e.target) !== (handle || el$$1)) {
        return;
      }
      if (el$$1.getAttribute('draggable') === 'false') {
        return;
      }
      this.sess = {
        el: el$$1,
        mx: parseInt(e.pageX, 10),
        my: parseInt(e.pageY, 10),
        ex: parseInt(s.left, 10) || 0,
        ey: parseInt(s.top, 10) || 0,
        info: {},
        bc: getCoordinates(el$$1),
        zIndex: el$$1.style.zIndex,
      };
      if (!this.sess.zIndex || Number(this.sess.zIndex) < highz - 1) {
        el$$1.style.zIndex = highz;
      }
      tryInvoke(this.onstart, {
        ...this.sess,
        droptarget: this.testDrops(this.sess.mx, this.sess.my),
      });
      this.boundEvents = {
        drag: event2 => this.drag(event2),
        drop: event2 => this.drop(event2),
      };
      document.addEventListener('mousemove', this.boundEvents.drag);
      document.addEventListener('mouseup', this.boundEvents.drop);
      this.drag(e);
    }

    drag(event) {
      const e = new Event$1(event).cancel();
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
        sy: this.sess.ey,
      };
      this.sess.info = sess;
      tryInvoke(this.ondrag, sess);
      if (
        sess.droptarget
        && tmp !== sess.droptarget
      ) {
        tryInvoke(this.ondragover, sess);
      }
      if (
        tmp
        && sess.droptarget !== tmp
      ) {
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
      droppables.forEach((droppable) => {
        if (droppable === this.sess.el || isChildOf(droppable, this.sess.el)) {
          return;
        }
        z = getCoordinates(droppable);
        if (
          max[0] > z.w
          && max[1] > z.h
          && a >= z.x
          && b >= z.y
          && a <= z.xw
          && b <= z.yh
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

    apply(el$$1, t) {
      if (Array.isArray(el$$1)) {
        el$$1.forEach(el2 => this.apply(el2));
        return this;
      }

      let pos = getComputedStyle(el$$1, '');
      pos = pos.position;
      if (!pos || pos === 'static') {
        el$$1.style.position = 'relative';
      }
      (t || el$$1).onmousedown = t
        ? e => this.start(e, el$$1, t)
        : e => this.start(e, el$$1);
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

    reset(el$$1 = this.sess.el) {
      el$$1.style.top = 0;
      el$$1.style.left = 0;
      return this;
    }
  }

  class Window {
    constructor() {
      assign(this, {
        title: 'Title',
        wait: true,
        content: 'Content',
        open: false,
        useoverlay: false,
        minimizable: true,
        resize: false,
        className: '',
        pos: 'center',
        zIndex: getHighestZIndex(),
      });
    }

    create() {
      if (this.windowContainer) {
        // DOM already created
        return null;
      }
      const windowContainer = document.createElement('div');
      const titleBar = document.createElement('div');
      const contentContainer = document.createElement('div');
      const windowControls = document.createElement('div');
      const minimizeButton = document.createElement('div');
      const closeButton = document.createElement('div');
      const { pos } = this;

      this.windowContainer = windowContainer;
      if (this.id) {
        windowContainer.id = this.id;
      }
      this.contentcontainer = contentContainer;

      if (this.useOverlay) {
        toggleOverlay(true, this.zIndex);
      }
      windowContainer.className = `window${this.className ? ` ${this.className}` : ''}`;
      titleBar.className = 'title';
      contentContainer.className = 'content';
      if (this.minimizable) {
        minimizeButton.innerHTML = '-';
        minimizeButton.onclick = () => this.minimize();
      }
      closeButton.innerHTML = 'X';
      closeButton.onclick = () => this.close();
      windowControls.appendChild(minimizeButton);
      windowControls.appendChild(closeButton);
      windowControls.className = 'controls';
      titleBar.innerHTML = this.title;
      contentContainer.innerHTML = this.content;
      titleBar.appendChild(windowControls);
      windowContainer.appendChild(titleBar);
      windowContainer.appendChild(contentContainer);
      document.body.appendChild(windowContainer);

      if (this.resize) {
        const targ = windowContainer.querySelector(this.resize);
        if (!targ) {
          throw new Error('Resize target not found');
        }
        targ.style.width = `${targ.clientWidth}px`;
        targ.style.height = `${targ.clientHeight}px`;
        const rsize = document.createElement('div');
        rsize.className = 'resize';
        windowContainer.appendChild(rsize);
        rsize.style.left = `${windowContainer.clientWidth - 16}px`;
        rsize.style.top = `${windowContainer.clientHeight - 16}px`;
        new Drag()
          .boundingBox(100, 100, Infinity, Infinity)
          .addListener({
            ondrag(a) {
              const w = parseFloat(targ.style.width) + a.dx;
              const h = parseFloat(targ.style.height) + a.dy;
              targ.style.width = `${w}px`;
              if (w < windowContainer.clientWidth - 20) {
                targ.style.width = `${windowContainer.clientWidth}px`;
              } else {
                rsize.style.left = `${windowContainer.clientWidth - 16}px`;
              }
              targ.style.height = `${h}px`;
            },
            ondrop() {
              rsize.style.left = `${windowContainer.clientWidth - 16}px`;
            },
          })
          .apply(rsize);
        targ.style.width = `${windowContainer.clientWidth}px`;
        rsize.style.left = `${windowContainer.clientWidth - 16}px`;
      }

      const s = windowContainer.style;
      s.zIndex = this.zIndex + 5;

      if (this.wait) {
        onImagesLoaded(
          windowContainer.querySelectorAll('img'),
          () => {
            this.setPosition(pos);
          },
          2000,
        );
      } else this.setPosition(pos);

      this.drag = new Drag()
        .autoZ()
        .noChildActivation()
        .boundingBox(
          0, 0,
          document.documentElement.clientWidth - 50,
          document.documentElement.clientHeight - 50,
        )
        .apply(windowContainer, titleBar);
      windowContainer.close = () => this.close();
      windowContainer.minimize = this.minimize;
      return windowContainer;
    }

    close() {
      if (!this.windowContainer) {
        return;
      }
      document.body.removeChild(this.windowContainer);
      this.windowContainer = null;
      if (this.onclose) this.onclose();
      if (this.useOverlay) toggleOverlay(false);
    }

    minimize() {
      const c = this.windowContainer;
      const isMinimized = c.classList.contains('minimized');
      c.classList.toggle('minimized');
      if (isMinimized) {
        c.removeAttribute('draggable');
        this.setPosition(this.oldpos, 0);
      } else {
        c.setAttribute('draggable', 'false');
        const wins = Array.from(document.querySelectorAll('.window'));
        const width = wins.reduce((w, window) => {
          if (window.classList.contains('minimized')) {
            return w + Number(window.clientWidth);
          }
          return w;
        }, 0);
        this.oldpos = this.getPosition();
        this.setPosition(`bl ${width} 0`, false);
      }
    }

    setPosition(pos, animate) {
      const d1 = this.windowContainer;
      let x = 0;
      let y = 0;
      const cH = document.documentElement.clientHeight;
      const cW = document.documentElement.clientWidth;
      const position = pos.match(/(\d+) (\d+)/);
      if (position) {
        x = Number(position[1]);
        y = Number(position[2]);
      }
      x = Math.floor(x);
      y = Math.floor(y);
      if (pos.charAt(1) === 'r') {
        x = cW - x - d1.clientWidth;
      }
      switch (pos.charAt(0)) {
        case 'b':
          y = cH - y - d1.clientHeight;
          break;
        default:
        case 'c':
          y = (cH - d1.clientHeight) / 2;
          x = (cW - d1.clientWidth) / 2;
          break;
      }
      x = Math.floor(x);
      y = Math.floor(y);

      if (x < 0) x = 0;
      if (y < 0) y = 0;
      d1.style.left = `${x}px`;
      if (this.animate || animate) {
        new Animation(d1, 10)
          .add('top', `${y - 100}px`, `${y}px`)
          .play();
      } else d1.style.top = `${y}px`;
      this.pos = pos;
    }

    getPosition() {
      const s = this.windowContainer.style;
      return `tl ${parseFloat(s.left)} ${parseFloat(s.top)}`;
    }
  }

  Window.close = function close(window) {
    let element = window;
    do {
      if (element.close) {
        element.close();
        break;
      }
      element = element.offsetParent;
    } while (element);
  };

  class Sound {
    constructor() {
      this.soundCache = {};
    }

    load(title, file, autoplay) {
      const audio = new Audio();
      this.soundCache[title] = audio;
      audio.autoplay = !!autoplay;
      audio.src = file;
    }

    play(title) {
      this.soundCache[title].play();
    }

    loadAndPlay(title, file) {
      this.load(title, file, true);
    }
  }

  // Sound is a singleton
  var Sound$1 = new Sound();

  /* eslint-disable */
  // TODO: Remove this file

  class Uploader {
    constructor() {
      this.uploaders = [];
    }

    listenerHandler(id, action, args) {
      // moving arguments around
      switch (action) {
        case 'addfile':
          args[0].id = args[1];
          args = args[0];
          args.upload = function (url) {
            Uploader.upload(id, this, url);
          };
          args = [args];
          break;
        case 'startupload':
          args[0].id = args[1];
          args = [args[0]];
          break;
        case 'progress':
          args[0].id = args[1];
          args.splice(1, 1);
          break;
        case 'error':
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

    createButton() {
      const d = document.createElement('div');
      d.className = 'uploadbutton';
      d.innerHTML = 'Add File(s)';
      return [d, this.create(d)];
    }

    create(el, w, h, url) {
      const nid = this.uploaders.length;
      const swf = JAX.SWF('Script/uploader.swf', `uploader${nid}`, {
        width: w || '100%',
        height: h || '100%',
        allowScriptAccess: 'sameDomain',
        wmode: 'transparent',
        flashvars: `id=${nid}`,
      });

      const s = swf.style;
      s.position = 'absolute';
      s.left = '0px';
      s.top = '0px';
      el.style.position = 'relative';
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

  /* global RUN, globalsettings */

  /**
   * These are all of the possible commands
   * that the server can send to the client.
   */
  var Commands = {
    script(a) {
      // eslint-disable-next-line
      eval(a[0]);
    },
    error(a) {
      alert(a[0]);
    },
    alert(a) {
      alert(a[0]);
    },
    addclass([selector, className]) {
      const el$$1 = document.querySelector(selector);
      if (el$$1) {
        el$$1.classList.add(className);
      }
    },
    title(a) {
      document.title = a;
    },
    update([sel, html, shouldHighlight]) {
      let selector = sel;
      const paths = Array.from(document.querySelectorAll('.path'));
      if (selector === 'path' && paths.length > 1) {
        paths.forEach((path) => {
          path.innerHTML = html;
          gracefulDegrade(path);
        });
        return;
      }
      if (!selector.match(/^\W/)) {
        selector = `#${selector}`;
      }
      const el$$1 = document.querySelector(selector);
      if (!el$$1) return;
      el$$1.innerHTML = html;
      if (shouldHighlight) {
        new Animation(el$$1)
          .dehighlight()
          .play();
      }
      gracefulDegrade(el$$1);
    },
    removeel(a) {
      const el$$1 = document.querySelector(a[0]);
      if (el$$1) el$$1.parentNode.removeChild(el$$1);
    },
    overlay: toggleOverlay,
    back() {
      window.history.back();
    },
    goto(args) {
      let [selector] = args;
      if (!selector.match(/^\W/)) {
        selector = `#${selector}`;
      }
      const el$$1 = document.querySelector(selector);
      scrollTo(getCoordinates(el$$1).y);
    },
    setloc(a) {
      document.location = `#${a}`;
      RUN.stream.lastURL = `?${a}`;
    },
    setstatus([className]) {
      const status = document.querySelector('#status');
      if (status) {
        status.className = className;
      }
    },
    appendrows(a) {
      const table = document.querySelector(a[0]);
      const span = document.createElement('span');
      span.innerHTML = `<table>${a[1]}</table>`;
      const vtbody = span.getElementsByTagName('tbody')[0];
      // table=table.getElementsByTagName('tbody')[0],
      gracefulDegrade(vtbody);
      table.appendChild(vtbody);
    },
    location([path]) {
      if (path.charAt(0) === '?') RUN.stream.location(path);
      else {
        document.location = path;
      }
    },
    enable([selector]) {
      const el$$1 = document.querySelector(`#${selector}`);
      if (el$$1) {
        el$$1.disabled = false;
      }
    },
    addshout([message]) {
      const ss = Array.from(document.querySelectorAll('#shoutbox .shout'));
      let x;
      const span = document.createElement('span');
      span.innerHTML = message;
      const div = span.firstChild;
      ss[0].parentNode.insertBefore(div, ss[0]);
      while (ss.length > globalsettings.shoutlimit - 1) {
        x = ss.pop();
        x.parentNode.removeChild(x);
      }
      new Animation(div)
        .dehighlight()
        .play();
      if (globalsettings.sound_shout) Sound$1.play('sbblip');
      gracefulDegrade(div);
    },
    tick([html]) {
      const ticker = document.querySelector('#ticker');
      let tick = document.createElement('div');
      tick.className = 'tick';
      tick.innerHTML = html;
      tick.style.display = 'none';
      tick.style.overflow = 'hidden';
      ticker.insertBefore(tick, ticker.firstChild);
      let h = getComputedStyle(tick);
      h = h.height;
      tick.style.height = '0px';
      new Animation(tick)
        .add('height', '0px', h)
        .play();
      const ticks = Array.from(ticker.querySelectorAll('.tick'));
      const l = ticks.length;
      tick.style.display = 'block';
      if (l > 100) {
        for (let x = 100; x < l; x += 100) {
          tick = ticks[x];
          if (!tick.bonked) {
            tick = ticks[x];
            new Animation(tick, 30, 500)
              .add('opacity', '1', '0')
              .then((el$$1) => {
                el$$1.parentNode.removeChild(el$$1);
              })
              .play();
            tick.bonked = true;
          }
        }
      }
    },
    im([fromId, fromName, message, fromMe, title]) {
      let messagesContainer = document.querySelector(`#im_${fromId} .ims`);
      flashTitle(`New message from ${fromName}!`);
      const { webkitNotifications } = window;
      if (
        !document.hasFocus()
        && webkitNotifications
        && webkitNotifications.checkPermission() === 0
      ) {
        const notify = webkitNotifications.createNotification(
          '',
          `${fromName} says:`,
          message,
        );
        notify.show();
        notify.onclick = () => {
          window.focus();
          notify.cancel();
        };
      }
      if (!messagesContainer) {
        const imWindow = new Window();
        imWindow.title = `${fromName
      } <a href="#" onclick="IMWindow.menu(event,${
        fromId
      });return false;">&rsaquo;</a>`;
        imWindow.content = "<div class='ims'></div><div class='offline'>This user may be offline</div><div><form onsubmit='return RUN.submitForm(this,1)' method='post'><input type='hidden' name='im_uid' value='%s' /><input type='text' name='im_im' /><input type='hidden' name='act' value='blank' /></form></div>".replace(
          /%s/g,
          fromId,
        );
        imWindow.className = 'im';
        imWindow.resize = '.ims';
        imWindow.animate = true;

        const win = imWindow.create();
        gracefulDegrade(win);
        win.id = `im_${fromId}`;
        win.onclick = () => {
          win.querySelector('form').im_im.focus();
        };
        win.onclick();
        messagesContainer = document.querySelector(`#im_${fromId} .ims`);
        const test = getComputedStyle(messagesContainer);
        messagesContainer.style.width = test.width;
        messagesContainer.style.height = test.height;
        if (message && globalsettings.sound_im) Sound$1.play('imnewwindow');
      }
      if (message) {
        const d = document.createElement('div');
        const isAction = message.substring(0, 3) === '/me';
        if (isAction) {
          d.className = 'action';
          /* eslint-disable no-param-reassign */
          message = message.substring(3);
          fromName = `***${fromName}`;
          /* eslint-enable no-param-reassign */
        }
        d.classList.add(fromMe ? 'you' : 'them');
        if (!fromMe) {
          document.querySelector(`#im_${fromId}`).classList.remove('offline');
        }
        d.innerHTML = `<a href='?act=vu${
        fromMe || parseInt(fromId, 10)
      }' class='name'>${
        fromName
      }</a> ${
        !isAction ? ': ' : ''
      }${message}`;
        d.title = title;
        const test = messagesContainer.scrollTop > (
          messagesContainer.scrollHeight - messagesContainer.clientHeight - 50
        );
        messagesContainer.appendChild(d);
        if (test) {
          messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        new Animation(d)
          .dehighlight()
          .play();
        gracefulDegrade(d);
        if (!messagesContainer && globalsettings.sound_im) Sound$1.play('imbeep');
      }
    },
    imtoggleoffline(a) {
      document.querySelector(`#im_${a}`).classList.add('offline');
    },
    window([options]) {
      if (options.id && document.getElementById(options.id)) return;
      const win = new Window();
      win.title = options.title;
      win.content = options.content;
      win.minimizable = options.minimizable || 0;
      win.useoverlay = options.useoverlay || 0;
      win.animate = options.animate !== undefined ? options.animate : true;
      win.resize = options.resize || false;
      win.className = options.className || '';
      if (options.onclose) {
        // eslint-disable-next-line no-eval
        win.onclose = eval(options.onclose);
      }
      if (options.pos) win.pos = options.pos;

      const winElement = win.create();
      winElement.id = options.id || '';
      gracefulDegrade(winElement);
    },
    openbuddylist([options]) {
      const buddylist = document.querySelector('#buddylist');
      if (!buddylist) {
        const win = new Window();
        win.id = 'buddylist';
        win.content = options.content;
        win.title = options.title;
        win.pos = 'tr 20 20';
        win.animate = 0;
        win.wait = false;
        win.onclose = () => {
          document.cookie = 'buddylist=0';
        };
        win.resize = '.content';
        win.create();
      } else {
        buddylist.querySelector('.content').innerHTML = options.content;
      }
    },
    closewindow([windowSelector]) {
      const el$$1 = document.querySelector(windowSelector);
      Window.close(el$$1);
    },
    onlinelist(a) {
      const statusers = document.querySelector('#statusers');
      if (!statusers) {
        return;
      }
      a[0].forEach(([memberId, groupId, status, name, tooltip]) => {
        let link = document.querySelector(`#statusers .user${memberId}`);
        if (!link) {
          link = document.createElement('a');
          if (!Number.isNaN(parseInt(memberId, 10))) {
            link.href = `?act=vu${memberId}`;
          }
          link.innerHTML = name;
          link.onclick = () => {
            RUN.location(link.getAttribute('href'));
          };
        }
        link.className = `user${
        memberId
      } mgroup${
        groupId
      } ${
        status ? ` ${status}` : ''}`;
        if (tooltip) {
          link.onmouseover = () => {
            openTooltip(this, this.title);
          };
        }
        link.title = tooltip;
        if (status !== 'idle') {
          if (statusers.firstChild) {
            statusers.insertBefore(link, statusers.firstChild);
          } else statusers.appendChild(link);
        }
      });
    },
    setoffline(a) {
      const statusers = document.querySelector('#statusers');
      const ids = a[0].split(',');
      ids.forEach((id) => {
        const link = document.querySelector(`#statusers .user${id}`);
        if (link) {
          statusers.removeChild(link);
        }
      });
    },

    scrollToPost([postId, wait]) {
      const el$$1 = document.getElementById(`pid_${postId}`);
      let pos;
      if (!el$$1) {
        return false;
      }
      onImagesLoaded(
        document.getElementById('page').getElementsByTagName('img'),
        () => {
          pos = getCoordinates(el$$1);
          scrollTo(pos.y);
        },
        wait ? 10 : 1000,
      );
      return true;
    },
    updateqreply(a) {
      const qreply = document.querySelector('#qreply');
      if (qreply) {
        qreply
          .querySelector('textarea')
          .focus();
        qreply.querySelector('textarea').value += a[0];
      }
    },
    newmessage([message, fromMID]) {
      let notification = document.querySelector('#notification');
      const num = document.querySelector('#num-messages');
      if (num) num.innerHTML = parseInt(num.innerHTML, 10) + 1;
      if (!notification) {
        notification = document.createElement('div');
        notification.id = 'notification';
        document.body.appendChild(notification);
      }
      notification.style.display = '';
      notification.className = 'newmessage';
      notification.onclick = () => {
        notification.style.display = 'none';
        RUN.stream.location(`?act=ucp&what=inbox&view=${fromMID}`, 3);
      };
      notification.innerHTML = message;
    },

    playsound(a) {
      Sound$1.loadAndPlay(a[0], a[1], !!a[2]);
    },
    attachfiles() {
      const el$$1 = document.querySelector('#attachfiles');
      const u = Uploader$1.createButton();
      const d = document.createElement('div');
      d.className = 'files';
      d.appendChild(u[0]);
      replace(el$$1, d);
      u[1].addfile = (file) => {
        if (file.size > 5242880) {
          setTimeout(() => {
            alert("Files can't be over 5MB");
          }, 1000);
          return;
        }
        const f = document.createElement('div');
        f.className = 'file';
        f.innerHTML = `<div class='name'>${
        file.name
      }</div><div class='progressbar'><div class='progress' id='progress_${
        this.id
      }_${
        file.id
      }' style='width:0px'></div></div>`;
        d.appendChild(f);
        file.upload(
          `/index.php?act=post&uploadflash=1&sessid=${
          document.cookie.match('sid=([^;]+)')[1]}`,
        );
      };
      u[1].error = (error, content) => {
        const w = new Window();
        w.title = 'error';
        w.content = content;
        w.create();
      };
      u[1].progress = (file, b, c) => {
        document.querySelector(`#progress_${this.id}_${file.id}`).style.width = `${Math.round((b / c) * 100)}%`;
      };
      u[1].response = (response) => {
        document.querySelector('#pdedit').editor.cmd(
          'inserthtml',
          `[attachment]${response}[/attachment]`,
        );
      };
    },
    listrating([postId, html]) {
      let prdiv = document.querySelector(`#postrating_${postId}`);
      let c;
      if (prdiv) {
        if (prdiv.style.display !== 'none') {
          new Animation(prdiv)
            .add('height', '200px', '0px')
            .then(() => {
              prdiv.style.display = 'none';
            })
            .play();
          return;
        } prdiv.style.display = 'block';
      } else {
        prdiv = document.createElement('div');
        prdiv.className = 'postrating_list';
        prdiv.id = `postrating_${postId}`;
        c = getCoordinates(document.querySelector(`#pid_${postId} .postrating`));
        prdiv.style.top = `${c.yh}px`;
        prdiv.style.left = `${c.x}px`;
        document.querySelector('#page').appendChild(prdiv);
      }
      prdiv.innerHTML = html;
      new Animation(prdiv).add('height', '0px', '200px').play();
    },
  };

  const UPDATE_INTERVAL = 5000;

  class Stream {
    constructor() {
      this.request = new Ajax({
        callback: request => this.handleRequestData(request),
      });
      this.lastURL = '';
      this.commands = Commands;
    }

    handleRequestData(xmlobj) {
      if (xmlobj.status !== 200) return;
      xmlobj.parsed = true;
      let { responseText } = xmlobj;
      const debug = document.querySelector('#debug');
      let softurl = false;
      if (typeof responseText !== 'string') responseText = '';
      if (debug) {
        debug.innerHTML = `<xmp>${responseText}</xmp>`;
      }
      let cmds = [];
      if (responseText.length) {
        try {
          cmds = JSON.parse(responseText);
        } catch (e) {
          cmds = [];
        }
        cmds.forEach(([cmd, ...args]) => {
          if (cmd === 'softurl') {
            softurl = true;
          } else if (this.commands[cmd]) {
            this.commands[cmd](args);
          }
        });
      }
      if (xmlobj.type >= 2) {
        const a = xmlobj.url.substring(1);
        if (!softurl) {
          document.location = `#${a}`;
          this.lastURL = a;
          if (Event.onPageChange) Event.onPageChange();
        } else if (document.location.hash.substring(1) === a) document.location = '#';
      }
      this.pollData();
    }

    location(path, b) {
      let a = path.split('?');
      a = a[1] || a[0];
      this.request.load(`?${a}`, null, null, null, b || 2);
      this.busy = true;
      return false;
    }

    load(...args) {
      this.request.load(...args);
    }

    loader() {
      this.request.load(`?${this.lastURL}`);
      return true;
    }

    pollData(isEager) {
      if (isEager) {
        this.loader();
      }
      clearTimeout(this.timeout);
      if (document.cookie.match(`actw=${window.name}`)) {
        this.timeout = setTimeout(() => this.loader(), UPDATE_INTERVAL);
      }
    }

    updatePage() {
      // this function makes the back/forward buttons actually do something,
      // using anchors
      const location = document.location.hash.substring(1) || '';
      if (location !== this.lastURL) {
        this.location(location, '3');
      }
    }
  }

  class AppState {
    onAppReady() {
      this.stream = new Stream();

      {
        gracefulDegrade(document.body);
      }

      updateDates();
      setInterval(updateDates, 1000 * 30);

      this.stream.pollData();
      setInterval(() => this.stream.updatePage, 200);

      if (document.location.toString().indexOf('?') > 0) {
        const hash = `#${document.location.search.substr(1)}`;
        {
          window.history.replaceState({}, '', `./${hash}`);
        }
      }


      // Load sounds
      Sound$1.load('sbblip', './Sounds/blip.mp3', false);
      Sound$1.load('imbeep', './Sounds/receive.mp3', false);
      Sound$1.load('imnewwindow', './Sounds/receive.mp3', false);

      document.cookie = 'buddylist=0';
    }

    submitForm(form, clearFormOnSubmit = false) {
      const names = [];
      const values = [];
      const submit = form.submitButton;

      Array.from(form.elements).forEach((inputField) => {
        if (!inputField.name || inputField.type === 'submit') {
          return;
        }

        if (inputField.type === 'select-multiple') {
          inputField.options
            .filter(option => option.selected)
            .forEach((option) => {
              names.push(`${inputField.name}[]`);
              values.push(option.value);
            });
          return;
        }

        if ((inputField.type === 'checkbox' || inputField.type === 'radio') && !inputField.checked) {
          return;
        }
        names.push(inputField.name);
        values.push(inputField.value);
      });

      if (submit) {
        names.push(submit.name);
        values.push(submit.value);
      }
      this.stream.load('?', 0, [names, values]);
      if (clearFormOnSubmit) {
        form.reset();
      }
      this.stream.pollData();
      return false;
    }

    handleQuoting(a) {
      this.stream.load(`${a.href}&qreply=${document.querySelector('#qreply') ? '1' : '0'}`);
    }

    setWindowActive() {
      document.cookie = `actw=${window.name}`;
      stopTitleFlashing();
      this.stream.pollData();
    }
  }

  const RUN$1 = new AppState();

  onDOMReady(() => {
    RUN$1.onAppReady();
  });
  onDOMReady(() => {
    window.name = Math.random();
    RUN$1.setWindowActive();
    window.addEventListener('onfocus', () => {
      RUN$1.setWindowActive();
    });
  });

  return RUN$1;

}());

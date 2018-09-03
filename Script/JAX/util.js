import {
  insertAfter,
  getHighestZIndex
} from './el';
import Animation from './animation';
import Browser from './browser';

// This file is just a dumping ground until I can find better homes for these

export const assign = function(a, b) {
  Object.assign(a, b);
};

export const gracefulDegrade = function(a) {
  if (typeof RUN != "undefined") RUN.updateDates();
  var tmp;
  var links = a.getElementsByTagName("a");
  var l = links.length;
  var x;
  var old;
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

export const convertSwitches = function(switches) {
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

export const checkAll = function(checkboxes, value) {
  for (var x = 0; x < checkboxes.length; x++) checkboxes[x].checked = value;
};

export const onImagesLoaded = function(imgs, f, timeout) {
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

export const handleTabs = function(e, a, f) {
  var e = e || window.event;
  var el = e.target || e.srcElement;
  var act;
  if (el.tagName.toLowerCase() != "a") return;
  if (f) el = f(el);
  act = a.querySelector(".active");
  if (act) act.className = "";
  el.className = "active";
  el.blur();
};

export const toggle = function(a) {
  if (a.style.display == "none") a.style.display = "";
  else a.style.display = "none";
};

export const collapse = function(a) {
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

export const toggleOverlay = function(show) {
  const dE = document.documentElement;
  show = parseInt(show);
  var ol = document.getElementById("overlay");
  var s;
  var op;
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

export const scrollTo = function(pos, el) {
  // make this animate/not animate later based on preferences
  const dB = document.body;
  const dE = document.documentElement;
  var el = el || (Browser.chrome ? dB : dE);
  var screenrel = parseFloat(dB.clientHeight) - parseFloat(dE.clientHeight);
  var top = parseFloat(el.scrollTop);
  var pos = screenrel < pos ? screenrel : pos;
  var diff = pos - top;
  el.scrollTop += diff;
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
}
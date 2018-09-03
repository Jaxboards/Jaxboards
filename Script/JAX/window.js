import Animation from './animation';
import Drag from './drag';
import { getHighestZIndex } from './el';
import { assign, toggleOverlay } from './util';

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
    var x = 0;
    var y = 0;
    var s;

    this.windowContainer = windowContainer;
    if (this.id) {
      windowContainer.id = this.id;
    }
    this.contentcontainer = contentContainer;

    if (this.useOverlay) {
      toggleOverlay(1, this.zIndex);
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
      new Drag()
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

    me.drag = new Drag()
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
    if (this.useOverlay) toggleOverlay(0);
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
};

Window.close = function(win) {
  do {
    if (win.close) {
      win.close();
      break;
    }
    win = win.offsetParent;
  } while (win);
};

export default Window;
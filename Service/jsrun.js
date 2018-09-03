var updatetime = 5000;
var useJSLinks = 2;
var adcounter = 0;

/* Returns the path to this script. */
function getJXBDBaseDir() {
  var elts = document.getElementsByTagName("script");
  for (var i = 0; i < elts.length; i++) {
    var elt = elts[i];
    if (elt.src.substr(elt.src.length - 8, 8) == "jsrun.js") {
      return elt.src.substr(0, elt.src.length - 8);
    }
  }
  return null;
}

function IMWindow(uid, uname) {
  if (!globalsettings.can_im) {
    return alert("You do not have permission to use this feature.");
  }
  RUN.stream.commands.im([uid, uname, false]);
}

IMWindow.menu = function(e, uid) {
  e = JAX.event(e).stopBubbling();
  var d = document.createElement("div");
  d.innerHTML = "loading";
  d.style.position = "absolute";
  d.style.left = e.pageX + "px";
  d.style.top = e.pageY + "px";
  d.style.zIndex = JAX.el.getHighestZIndex();
  d.id = "immenu";
  d.className = "immenu";
  document.body.appendChild(d);
  document.body.onclick = function(e) {
    e = JAX.event(e);
    if (e.srcElement != d && !JAX.el.isChildOf(e.srcElement, d)) {
      d.parentNode.removeChild(d);
    }
  };

  RUN.stream.load("?module=privatemessage&im_menu=" + uid);
};

function RUNF() {
  var me = this;
  var stream = (me.stream = new JAX.ajax());

  if (useJSLinks) JAX.gracefulDegrade(document.body);

  this.updateDates = function() {
    var dates = document.querySelectorAll(".autodate");
    var x;
    var parsed;
    if (!dates) return;
    for (x = 0; x < dates.length; x++) {
      parsed = dates[x].classList.contains("smalldate")
        ? JAX.smalldate(parseInt(dates[x].title))
        : JAX.date(parseInt(dates[x].title));
      if (parsed != dates[x].innerHTML) dates[x].innerHTML = parsed;
    }
  };
  this.updateDates();
  setInterval(this.updateDates, 1000 * 30);

  this.submitForm = function(a, b) {
    var names = [];
    var values = [];
    var x;
    var l = a.elements.length;
    var submit;
    var i;
    submit = a.submitButton;
    for (x = 0; x < l; x++) {
      if (!a[x].name || a[x].type == "submit") continue;
      if (a[x].type == "select-multiple") {
        for (i = 0; i < a[x].options.length; i++) {
          if (a[x].options[i].selected) {
            names.push(a[x].name + "[]");
            values.push(a[x].options[i].value);
          }
        }
        continue;
      }
      if ((a[x].type == "checkbox" || a[x].type == "radio") && !a[x].checked) {
        continue;
      }
      names.push(a[x].name);
      values.push(a[x].value);
    }
    if (submit) {
      names.push(submit.name);
      values.push(submit.value);
    }
    RUN.stream.load("?", 0, [names, values], 1, 1);
    if (b) a.reset();
    stream.donext();
    return false;
  };

  this.handleQuoting = function(a) {
    RUN.stream.load(a.href + "&qreply=" + (document.querySelector("#qreply") ? "1" : "0"));
  };

  this.setWindowActive = function() {
    document.cookie = "actw=" + window.name;
    JAX.stopTitleFlashing();
    RUN.stream.donext();
  };

  // page change event handler
  // Setup Stream
  stream.donext = function(a) {
    if (a) {
      stream.loader();
    }
    clearTimeout(stream.timeout);
    if (document.cookie.match("actw=" + window.name)) {
      stream.timeout = setTimeout(stream.loader, updatetime);
    }
  };
  stream.setup.callback = function(xmlobj) {
    if (xmlobj.status != 200) return;
    else xmlobj.parsed = true;
    var xml = xmlobj.responseText;
    var db = document.querySelector("#debug");
    var softurl = false;
    if (typeof xml != "string") xml = "";
    if (db) db.innerHTML = "<xmp>" + xml + "</xmp>";
    var x;
    var cmd;
    var cmds = [];
    if (xml.length) {
      try {
        cmds = eval("(" + xml + ")");
      } catch (e) {
        cmds = [];
      }
      for (x = 0; x < cmds.length; x++) {
        cmd = cmds[x].shift();
        if (cmd == "softurl") {
          softurl = true;
          continue;
        }
        if (stream.commands[cmd]) {
          cmd = stream.commands[cmd](cmds[x]);
        }
      }
    }
    if (xmlobj.type >= 2) {
      var a = xmlobj.url.substring(1);
      if (!softurl) {
        document.location = "#" + a;
        stream.lastURL = a;
        if (JAX.event.onPageChange) JAX.event.onPageChange();
      } else {
        if (document.location.hash.substring(1) == a) document.location = "#";
      }
    }
    stream.donext();
  };
  stream.lastURL = "";
  stream.setup.method = 1;
  stream.location = function(a, b) {
    a = a.split("?");
    a = a[1] || a[0];
    stream.load("?" + a, null, null, null, b || 2);
    stream.busy = true;
    return false;
  };
  stream.locationfunc = function() {
    void 0;
    RUN.stream.location(this.getAttribute("href"));
    return false;
  };
  stream.loader = function() {
    stream.load("?" + stream.lastURL);
    return true;
  };
  stream.updatePage = function() {
    // this function makes the back/forward buttons actually do something,
    // using anchors
    var l;
    if ((l = document.location.hash.substring(1) || "") != stream.lastURL) {
      stream.location(l, "3");
    }
  };
  stream.donext();
  setInterval(stream.updatePage, 200);

  // Commands
  stream.commands = {
    script: function(a) {
      eval(a[0]);
    },
    error: function(a) {
      alert(a[0]);
    },
    alert: function(a) {
      alert(a[0]);
    },
    addclass: function(a) {
      document.querySelector(a[0]).classList.add(a[1]);
    },
    title: function(a) {
      document.title = a;
    },
    update: function(a) {
      var el = a[0];
      var paths = document.querySelectorAll(".path");
      if (el == "path" && paths.length > 1) {
        for (var x = 0; x < paths.length; x++) {
          paths[x].innerHTML = a[1];
          JAX.gracefulDegrade(paths[x]);
        }
        return;
      }
      if (!el.match(/^\W/)) el = "#" + el;
      el = document.querySelector(el);
      if (!el) return;
      el.innerHTML = a[1];
      if (a[2]) {
        JAX.sfx(el)
          .dehighlight()
          .play();
      }
      JAX.gracefulDegrade(el);
    },
    removeel: function(a) {
      var el = document.querySelector(a[0]);
      if (el) el.parentNode.removeChild(el);
    },
    overlay: JAX.overlay,
    back: function() {
      history.back();
    },
    goto: function(a) {
      a = a[0];
      if (!a.match(/^\W/)) a = "#" + a;
      a = document.querySelector(a);
      JAX.scrollTo(JAX.el.getCoordinates(a).y);
    },
    setloc: function(a) {
      document.location = "#" + a;
      stream.lastURL = "?" + a;
    },
    setstatus: function(a) {
      var status = document.querySelector("#status");
      if (status) {
        status.className = a[0];
      }
    },
    appendrows: function(a) {
      var table = document.querySelector(a[0]);
      var span = document.createElement("span");
      span.innerHTML = "<table>" + a[1] + "</table>";
      var vtbody = span.getElementsByTagName("tbody")[0];
      // table=table.getElementsByTagName('tbody')[0],
      JAX.gracefulDegrade(vtbody);
      table.appendChild(vtbody);
    },
    location: function(a) {
      if (a[0].charAt(0) == "?") RUN.stream.location(a[0]);
      else document.location = a[0];
    },
    enable: function(a) {
      a = document.querySelector('#' + a[0]);
      if (a) {
        a.disabled = false;
      }
    },
    addshout: function(a) {
      var a = a[0];
      var ss = document.querySelectorAll("#shoutbox .shout");
      var x;
      var span = document.createElement("span");
      var div;
      span.innerHTML = a;
      div = span.firstChild;
      ss[0].parentNode.insertBefore(div, ss[0]);
      while (ss.length > globalsettings.shoutlimit - 1) {
        x = ss.pop();
        x.parentNode.removeChild(x);
      }
      JAX.sfx(div)
        .dehighlight()
        .play();
      if (globalsettings.sound_shout) Sound.play("sbblip");
      JAX.gracefulDegrade(div);
    },
    tick: function(a) {
      var ticker = document.querySelector("#ticker");
      var tick = document.createElement("div");
      tick.className = "tick";
      tick.innerHTML = a[0];
      tick.style.display = "none";
      tick.style.overflow = "hidden";
      ticker.insertBefore(tick, ticker.firstChild);
      var h = JAX.el.getComputedStyle(tick);
      h = h.height;
      tick.style.height = "0px";
      JAX.sfx(tick)
        .add("height", "0px", h)
        .play();
      var ticks = ticker.querySelectorAll(".tick");
      var l = ticks.length;
      tick.style.display = "block";
      if (l > 100) {
        for (var x = 100; x < l; x++) {
          tick = ticks[x];
          if (!tick.bonked) {
            tick = ticks[x];
            JAX.sfx(tick, 30, 500)
              .add("opacity", "1", "0")
              .then(function(el) {
                el.parentNode.removeChild(el);
              })
              .play();
            tick.bonked = true;
          }
        }
      }
    },
    im: function(a) {
      var sb = document.querySelector("#im_" + a[0] + " .ims");
      var test;
      JAX.flashTitle("New message from " + a[1] + "!");
      if (
        !document.hasFocus() &&
        window.webkitNotifications &&
        webkitNotifications.checkPermission() == 0
      ) {
        var notify = webkitNotifications.createNotification(
          "",
          a[1] + " says:",
          a[2]
        );
        notify.show();
        notify.onclick = function() {
          window.focus();
          notify.cancel();
        };
      }
      if (!sb) {
        var win = new JAX.window();
        win.title =
          a[1] +
          ' <a href="#" onclick="IMWindow.menu(event,' +
          a[0] +
          ');return false;">&rsaquo;</a>';
        win.content = "<div class='ims'></div><div class='offline'>This user may be offline</div><div><form onsubmit='return RUN.submitForm(this,1)' method='post'><input type='hidden' name='im_uid' value='%s' /><input type='text' name='im_im' /><input type='hidden' name='act' value='blank' /></form></div>".replace(
          /%s/g,
          a[0]
        );
        win.className = "im";
        win.resize = ".ims";
        win.animate = true;
        win = win.create();
        JAX.gracefulDegrade(win);
        win.id = "im_" + a[0];
        win.onclick = function() {
          win.getElementsByTagName("form")[0].im_im.focus();
        };
        win.onclick();
        sb = document.querySelector("#im_" + a[0] + " .ims");
        test = JAX.el.getComputedStyle(sb);
        sb.style.width = test.width;
        sb.style.height = test.height;
        if (a[2] && globalsettings.sound_im) Sound.play("imnewwindow");
      }
      if (a[2]) {
        a[3] = parseInt(a[3]);
        var d = document.createElement("div");
        var l;
        var x;
        var act = a[2].substring(0, 3) == "/me";
        if (act) {
          d.className = "action";
          a[2] = a[2].substring(3);
          a[1] = "***" + a[1];
        }
        d.classList.add(a[3] ? "you" : "them");
        if (!a[3]) {
          document.querySelector("#im_" + a[0]).classList.remove("offline");
        }
        d.innerHTML =
          "<a href='?act=vu" +
          (a[3] || parseInt(a[0])) +
          "' class='name'>" +
          a[1] +
          "</a> " +
          (!act ? ": " : "") +
          a[2];
        d.title = a[4];
        test = sb.scrollTop > sb.scrollHeight - sb.clientHeight - 50;
        sb.appendChild(d);
        if (test) sb.scrollTop = sb.scrollHeight;
        JAX.sfx(d)
          .dehighlight()
          .play();
        JAX.gracefulDegrade(d);
        if (!win && globalsettings.sound_im) Sound.play("imbeep");
      }
    },
    imtoggleoffline: function(a) {
      document.querySelector("#im_" + a).classList.add("offline");
    },
    window: function(a) {
      a = a[0];
      if (a.id && document.getElementById(a.id)) return;
      var win = new JAX.window();
      win.title = a.title;
      win.content = a.content;
      win.minimizable = a.minimizable || 0;
      win.useoverlay = a.useoverlay || 0;
      win.animate = a.animate !== undefined ? a.animate : true;
      win.resize = a.resize || false;
      win.className = a.className || "";
      if (a.onclose) win.onclose = eval(a.onclose);
      if (a.pos) win.pos = a.pos;
      win = win.create();
      win.id = a.id || "";
      JAX.gracefulDegrade(win);
    },
    openbuddylist: function(a) {
      a = a[0];
      var buddylist = document.querySelector("#buddylist");
      var win;
      if (!buddylist) {
        win = new JAX.window();
        win.id = "buddylist";
        win.content = a.content;
        win.title = a.title;
        win.pos = "tr 20 20";
        win.animate = 0;
        win.wait = false;
        win.onclose = function() {
          document.cookie = "buddylist=0";
        };
        win.resize = ".content";
        win = win.create();
      } else {
        buddyList.querySelector(".content").innerHTML = a.content;
      }
    },
    closewindow: function(a) {
      a = document.querySelector(a[0]);
      JAX.window.close(a);
    },
    onlinelist: function(a) {
      var html = "";
      var tmp;
      var link;
      var statusers = document.querySelector("#statusers");
      var newlink;
      if (!statusers) return;
      for (x = 0; x < a[0].length; x++) {
        tmp = a[0][x];
        link = document.querySelector("#statusers .user" + tmp[0]);
        if (!link) {
          link = document.createElement("a");
          if (!isNaN(parseInt(tmp[0]))) link.href = "?act=vu" + tmp[0];
          link.innerHTML = tmp[3];
          link.onclick = RUN.stream.locationfunc;
        }
        link.className =
          "user" +
          tmp[0] +
          " mgroup" +
          tmp[1] +
          " " +
          (tmp[2] ? " " + tmp[2] : "");
        if (tmp[4]) {
          link.onmouseover = function() {
            JAX.tooltip(this, this.title);
          };
        }
        link.title = tmp[4];
        if (tmp[2] != "idle") {
          if (statusers.firstChild) {
            statusers.insertBefore(link, statusers.firstChild);
          } else statusers.appendChild(link);
        }
      }
    },
    setoffline: function(a) {
      var statusers = document.querySelector("#statusers");
      var ids = a[0].split(",");
      var x;
      var link;
      for (x = 0; x < ids.length; x++) {
        link = document.querySelector("#statusers .user" + ids[x]);
        if (link) statusers.removeChild(link);
      }
    },
    scrollToPost: function(a) {
      var el = document.getElementById("pid_" + a[0]);
      var pos;
      if (!el) return false;
      JAX.onImagesLoaded(
        document.getElementById("page").getElementsByTagName("img"),
        function() {
          pos = JAX.el.getCoordinates(el);
          JAX.scrollTo(pos.y);
        },
        a[1] ? 10 : 1000
      );
    },
    updateqreply: function(a) {
      var qreply = document.querySelector('#qreply');
      if (qreply) {
        qreply
          .querySelector("textarea")
          .focus();
        qreply.querySelector("textarea").value += a[0];
      }
    },
    newmessage: function(a) {
      var n = document.querySelector("#notification");
      var num = document.querySelector("#num-messages");
      if (num) num.innerHTML = parseInt(num.innerHTML) + 1;
      if (!n) {
        n = document.createElement("div");
        n.id = "notification";
        document.body.appendChild(n);
      }
      n.style.display = "";
      n.className = "newmessage";
      n.onclick = function() {
        n.style.display = "none";
        RUN.stream.location("?act=ucp&what=inbox&view=" + a[1], 3);
      };
      n.innerHTML = a[0];
    },
    playsound: function(a) {
      Sound.loadAndPlay(a[0], a[1], a[2] ? true : false);
    },
    attachfiles: function() {
      var el = document.querySelector("#attachfiles");
      var u = Uploader.createButton();
      var d = document.createElement("div");
      d.className = "files";
      d.appendChild(u[0]);
      JAX.el.replace(el, d);
      u[1].addfile = function(file) {
        if (file.size > 5242880) {
          setTimeout(function() {
            alert("Files can't be over 5MB");
          }, 1000);
          return;
        }
        var f = document.createElement("div");
        f.className = "file";
        f.innerHTML =
          "<div class='name'>" +
          file.name +
          "</div><div class='progressbar'><div class='progress' id='progress_" +
          this.id +
          "_" +
          file.id +
          "' style='width:0px'></div></div>";
        d.appendChild(f);
        file.upload(
          "/index.php?act=post&uploadflash=1&sessid=" +
            document.cookie.match("sid=([^;]+)")[1]
        );
      };
      u[1].error = function(error, content) {
        var w = new JAX.window();
        w.title = "error";
        w.content = content;
        w.create();
      };
      u[1].progress = function(file, b, c) {
        document.querySelector("#progress_" + this.id + "_" + file.id).style.width =
          Math.round((b / c) * 100) + "%";
      };
      u[1].response = function(response) {
        document.querySelector("#pdedit").editor.cmd(
          "inserthtml",
          "[attachment]" + response + "[/attachment]"
        );
      };
    },
    listrating: function(a) {
      var prdiv = document.querySelector("#postrating_" + a[0]);
      var c;
      if (prdiv) {
        if (prdiv.style.display != "none") {
          new JAX.sfx(prdiv)
            .add("height", "200px", "0px")
            .then(function() {
              prdiv.style.display = "none";
            })
            .play();
          return;
        } else prdiv.style.display = "block";
      } else {
        prdiv = document.createElement("div");
        prdiv.className = "postrating_list";
        prdiv.id = "postrating_" + a[0];
        c = JAX.el.getCoordinates(document.querySelector("#pid_" + a[0] + " .postrating"));
        prdiv.style.top = c.yh + "px";
        prdiv.style.left = c.x + "px";
        document.querySelector("#page").appendChild(prdiv);
      }
      prdiv.innerHTML = a[1];
      new JAX.sfx(prdiv).add("height", "0px", "200px").play();
    }
  };

  if (useJSLinks && document.location.toString().indexOf("?") > 0) {
    var hash = "#" + document.location.search.substr(1);
    if (useJSLinks == 2) {
      history.replaceState({}, "", "./" + hash);
    } else {
      document.location = hash;
    }
  }

  if (Sound) {
    basedir = getJXBDBaseDir();

    Sound.load("sbblip", basedir + "Sounds/blip.mp3", false);
    Sound.load("imbeep", basedir + "Sounds/receive.mp3", false);
    Sound.load("imnewwindow", basedir + "Sounds/receive.mp3", false);
  }

  document.cookie = "buddylist=0";
}

OnDomReady(function() {
  RUN = new RUNF();
});
OnDomReady(function() {
  window.name = Math.random();
  window.addEventListener("onfocus", function() {
    RUN.setWindowActive();
  });
});

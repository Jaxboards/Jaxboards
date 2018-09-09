/* global RUN, globalsettings */
/* eslint-disable no-alert */
import {
  gracefulDegrade,
  toggleOverlay,
  scrollTo,
  onImagesLoaded,
} from '../JAX/util';
import Animation from '../JAX/animation';
import {
  getCoordinates,
  getComputedStyle,
} from '../JAX/el';
import {
  flashTitle,
} from '../JAX/flashing-title';
import openTooltip from '../JAX/tooltip';
import Window from '../JAX/window';
import Sound from '../sound';

/**
 * These are all of the possible commands
 * that the server can send to the client.
 */
export default {
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
    const el = document.querySelector(selector);
    if (el) {
      el.classList.add(className);
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
    if (!/^\W/.test(selector)) {
      selector = `#${selector}`;
    }
    const el = document.querySelector(selector);
    if (!el) return;
    el.innerHTML = html;
    if (shouldHighlight) {
      new Animation(el)
        .dehighlight()
        .play();
    }
    gracefulDegrade(el);
  },
  removeel(a) {
    const el = document.querySelector(a[0]);
    if (el) el.parentNode.removeChild(el);
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
    const el = document.querySelector(selector);
    scrollTo(getCoordinates(el).y);
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
    const el = document.querySelector(`#${selector}`);
    if (el) {
      el.disabled = false;
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
    if (globalsettings.sound_shout) Sound.play('sbblip');
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
            .then((el) => {
              el.parentNode.removeChild(el);
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
      imWindow.content = "<div class='ims'></div><div class='offline'>This user may be offline</div><div><form data-ajax-form='resetOnSubmit' method='post'><input type='hidden' name='im_uid' value='%s' /><input type='text' name='im_im' /><input type='hidden' name='act' value='blank' /></form></div>".replace(
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
      if (message && globalsettings.sound_im) Sound.play('imnewwindow');
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
      if (!messagesContainer && globalsettings.sound_im) Sound.play('imbeep');
    }
  },
  imtoggleoffline(a) {
    document.querySelector(`#im_${a}`).classList.add('offline');
  },
  window([options]) {
    const existingWindow = options.id && options.id && document.getElementById(options.id);
    if (existingWindow) {
      existingWindow.querySelector('.title').innerHTML = options.title;
      existingWindow.querySelector('.content').innerHTML = options.content;
      return;
    }
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
  closewindow([windowSelector]) {
    const el = document.querySelector(windowSelector);
    if (el) {
      Window.close(el);
    }
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
    const el = document.getElementById(`pid_${postId}`);
    let pos;
    if (!el) {
      return false;
    }
    onImagesLoaded(
      document.getElementById('page').getElementsByTagName('img'),
      () => {
        pos = getCoordinates(el);
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
    Sound.loadAndPlay(a[0], a[1], !!a[2]);
  },
  attachfiles() {
    const el = document.querySelector('#attachfiles');
    el.addEventListener('click', () => {
      alert('Attaching files is under construction');
    });
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

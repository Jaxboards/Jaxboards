/* global RUN */
import {
  insertAfter,
  getHighestZIndex,
} from './el';
import Animation from './animation';
import Browser from './browser';
import DatePicker from './date-picker';
import scrollablepagelist from './scrollablepagelist';
import { imageResizer } from './image-resizer';
import makeImageGallery from './image-gallery';

// This file is just a dumping ground until I can find better homes for these

export function assign(a, b) {
  Object.assign(a, b);
}

/**
 * Tries to call a function, if it exists.
 * @param  {Function} method
 * @param  {...any} args
 * @return {any}
 */
export function tryInvoke(method, ...args) {
  if (method && typeof method === 'function') {
    return method(...args);
  }
  return null;
}

function convertSwitches(switches) {
  switches.forEach((switchElement) => {
    const div = document.createElement('div');
    div.className = switchElement.className.replace('switch', 'switch_converted');
    // eslint-disable-next-line no-param-reassign
    switchElement.style.display = 'none';
    if (!switchElement.checked) {
      div.style.backgroundPosition = 'bottom';
    }
    div.onclick = () => {
      // eslint-disable-next-line no-param-reassign
      switchElement.checked = !switchElement.checked;
      this.style.backgroundPosition = switchElement.checked ? 'top' : 'bottom';
      tryInvoke(switchElement.onclick);
    };
    insertAfter(div, switchElement);
  });
}

export function onImagesLoaded(imgs, callback, timeout) {
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
  imgs.forEach((img) => {
    if (dbj.imgs.includes(img.src) === false && !img.loaded) {
      dbj.imgs.push(img.src);
      img.addEventListener('onload', dbj.callback);
      // eslint-disable-next-line no-param-reassign
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

export function gracefulDegrade(a) {
  if (typeof RUN !== 'undefined') RUN.updateDates();
  const links = a.querySelectorAll('a');
  links.forEach((link) => {
    if (link.href) {
      if (link.getAttribute('href').charAt(0) === '?') {
        const oldclick = link.onclick;
        // eslint-disable-next-line no-param-reassign
        link.onclick = function onclick() {
          if (!oldclick || oldclick() !== false) {
            RUN.stream.location(this.getAttribute('href'));
          }
          return false;
        };
      } else if (link.getAttribute('href').substr(0, 4) === 'http') {
        // eslint-disable-next-line no-param-reassign
        link.target = '_BLANK';
      }
    }
  });
  convertSwitches(a.querySelectorAll('.switch'));

  const bbcodeimgs = document.querySelectorAll('.bbcodeimg');
  if (bbcodeimgs) {
    onImagesLoaded(
      bbcodeimgs,
      () => {
        // resizer on large images
        imageResizer(bbcodeimgs);

        // handle image galleries
        const galleries = document.querySelectorAll('.image_gallery');
        galleries.map(makeImageGallery);
      },
      2000,
    );
  }

  // Initialize page lists that scroll with scroll wheel
  const pages = a.querySelectorAll('.pages');
  if (pages.length) {
    pages.map(scrollablepagelist);
  }

  // Set up date pickers
  const dateElements = a.querySelectorAll('input.date');
  if (dateElements.length) {
    dateElements.forEach((inputElement) => {
      // eslint-disable-next-line no-param-reassign
      inputElement.onclick = () => DatePicker.init(this);
      // eslint-disable-next-line no-param-reassign
      inputElement.onkeydown = () => DatePicker.hide();
    });
  }
}

export function checkAll(checkboxes, value) {
  checkboxes.forEach((checkbox) => {
    // eslint-disable-next-line no-param-reassign
    checkbox.checked = value;
  });
}

export function handleTabs(e, a, f) {
  const activeClass = 'active';
  let el = e.target || e.srcElement;
  if (el.tagName.toLowerCase() !== 'a') {
    return;
  }
  if (f) {
    el = f(el);
  }
  const activeTab = a.querySelector('.active');
  if (activeTab) {
    activeTab.classList.remove(activeClass);
  }
  el.className = activeClass;
  el.blur();
}

export function toggle(a) {
  let display = 'none';
  if (a.style.display === display) {
    display = '';
  }
  // eslint-disable-next-line no-param-reassign
  a.style.display = display;
}

export function collapse(a) {
  const s = a.style;
  let fh = a.getAttribute('fullHeight');
  const b = a.parentNode;
  s.overflow = 'hidden';
  if (s.height === '0px') {
    new Animation(a, 5, 10, 0)
      .add('height', '0px', fh)
      .then(() => {
        b.classList.remove('collapsed');
      })
      .play();
  } else {
    if (!fh) {
      fh = `${a.clientHeight || a.offsetHeight}px`;
      a.setAttribute('fullHeight', fh);
    }
    new Animation(a, 5, 10, 0)
      .add('height', fh, '0px')
      .then(() => {
        b.classList.add('collapsed');
      })
      .play();
  }
}

export function toggleOverlay(show) {
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

export function scrollTo(pos, el = Browser.chrome ? document.body : document.documentElement) {
  const screenrel = (
    parseFloat(document.body.clientHeight)
    - parseFloat(document.documentElement.clientHeight)
  );
  const top = parseFloat(el.scrollTop);
  const position = screenrel < pos ? screenrel : pos;
  const diff = position - top;
  // eslint-disable-next-line no-param-reassign
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
  return me */
}
import { getHighestZIndex } from './el';
import Browser from './browser';
import { date, smalldate } from './date';

// This file is just a dumping ground until I can find better homes for these
export function assign(a, b) {
  return Object.assign(a, b);
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
    }
  };
  Array.from(imgs).forEach(img => {
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

export function updateDates() {
  const dates = Array.from(document.querySelectorAll('.autodate'));
  if (!dates) {
    return;
  }
  dates.forEach(el => {
    const timestamp = parseInt(el.title, 10);
    const parsed = el.classList.contains('smalldate')
      ? smalldate(timestamp)
      : date(timestamp);
    if (parsed !== el.innerHTML) {
      el.innerHTML = parsed;
    }
  });
}

export function toggleOverlay(show) {
  const dE = document.documentElement;
  let ol = document.getElementById('overlay');
  if (ol) {
    assign(ol.style, {
      zIndex: getHighestZIndex(),
      top: 0,
      height: `${dE.clientHeight}px`,
      width: `${dE.clientWidth}px`,
      display: show ? '' : 'none'
    });
  } else {
    if (!show) return;
    ol = document.createElement('div');
    ol.id = 'overlay';
    assign(ol.style, {
      height: `${dE.clientHeight}0px`,
      width: `${dE.clientWidth}0px`
    });
    dE.appendChild(ol);
  }
}

export function scrollTo(
  pos,
  el = Browser.chrome ? document.body : document.documentElement
) {
  const screenrel =
    parseFloat(document.body.clientHeight) -
    parseFloat(document.documentElement.clientHeight);
  const top = parseFloat(el.scrollTop);
  const position = screenrel < pos ? screenrel : pos;
  const diff = position - top;
  el.scrollTop += diff;
}

/**
 * Run a callback function either when the DOM is loaded and ready,
 * or immediately if the document is already loaded.
 * @param {Function} callback
 */
export function onDOMReady(callback) {
  if (document.readyState === 'complete') {
    callback();
  } else {
    document.addEventListener('DOMContentLoaded', callback);
  }
}

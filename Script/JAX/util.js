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
import {
  date,
  smalldate,
} from './date';
import tooltip from './tooltip';
import { selectAll } from './selection';
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
    const button = document.createElement('button');
    button.className = switchElement.className.replace('switch', 'switch_converted');
    switchElement.style.display = 'none';
    if (!switchElement.checked) {
      button.style.backgroundPosition = 'bottom';
    }
    button.addEventListener('click', () => {
      switchElement.checked = !switchElement.checked;
      button.style.backgroundPosition = switchElement.checked ? 'top' : 'bottom';
      switchElement.dispatchEvent(new Event('change'));
    });
    insertAfter(button, switchElement);
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

export function updateDates() {
  const dates = Array.from(document.querySelectorAll('.autodate'));
  if (!dates) {
    return;
  }
  dates.forEach((el) => {
    const timestamp = parseInt(el.title, 10);
    const parsed = el.classList.contains('smalldate')
      ? smalldate(timestamp)
      : date(timestamp);
    if (parsed !== el.innerHTML) {
      el.innerHTML = parsed;
    }
  });
}

export function collapse(element) {
  const s = element.style;
  let fh = element.dataset.fullHeight;
  const b = element.parentNode;
  s.overflow = 'hidden';
  if (s.height === '0px') {
    new Animation(element, 5, 10, 0)
      .add('height', '0px', fh)
      .then(() => {
        b.classList.remove('collapsed');
      })
      .play();
  } else {
    if (!fh) {
      fh = `${element.clientHeight || element.offsetHeight}px`;
      element.dataset.fullHeight = fh;
    }
    new Animation(element, 5, 10, 0)
      .add('height', fh, '0px')
      .then(() => {
        b.classList.add('collapsed');
      })
      .play();
  }
}

export function handleTabs(event, container, tabSelector) {
  const activeClass = 'active';
  let el = event.target;
  if (el.tagName.toLowerCase() !== 'a') {
    return;
  }
  if (tabSelector) {
    el = el.closest(tabSelector);
  }
  const activeTab = container.querySelector('.active');
  if (activeTab) {
    activeTab.classList.remove(activeClass);
  }
  el.className = activeClass;
  el.blur();
}

export function gracefulDegrade(a) {
  if (typeof RUN !== 'undefined') {
    updateDates();
  }

  // Special rules for all links
  const links = a.querySelectorAll('a');
  links.forEach((link) => {
    // Hande links with tooltips
    if (link.dataset.useTooltip) {
      link.addEventListener('mouseover', () => tooltip(link));
    }

    // Make all links load through AJAX
    if (link.href) {
      const href = link.getAttribute('href');
      if (href.charAt(0) === '?') {
        const oldclick = link.onclick;
        link.addEventListener('click', (event) => {
          // Some links have an onclick that returns true/false based on whether
          // or not the link should execute.
          if (!oldclick || oldclick.call(link) !== false) {
            RUN.stream.location(href);
          }
          event.preventDefault();
        });

      // Open external links in a new window
      } else if (link.getAttribute('href').substr(0, 4) === 'http') {
        link.target = '_BLANK';
      }
    }
  });

  // Convert checkboxes to icons (checkmark and X)
  convertSwitches(Array.from(a.querySelectorAll('.switch')));

  // Handle image hover magnification
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
      inputElement.onclick = () => DatePicker.init(inputElement);
      inputElement.onkeydown = () => DatePicker.hide();
    });
  }

  // Make BBCode code blocks selectable when clicked
  const codeBlocks = a.querySelectorAll('.bbcode.code');
  codeBlocks.forEach((codeBlock) => {
    codeBlock.addEventListener('click', () => selectAll(codeBlock));
  });

  // Make collapse boxes collapsible
  const collapseBoxes = a.querySelectorAll('.collapse-box');
  collapseBoxes.forEach((collapseBox) => {
    const collapseButton = collapseBox.querySelector('.collapse-button');
    const collapseContent = collapseBox.querySelector('.collapse-content');
    collapseButton.addEventListener('click', () => {
      collapse(collapseContent);
    });
  });

  // Wire up AJAX forms
  const ajaxForms = a.querySelectorAll('form[data-ajax-form]');
  ajaxForms.forEach((ajaxForm) => {
    const resetOnSubmit = ajaxForm.dataset.ajaxForm === 'resetOnSubmit';
    ajaxForm.addEventListener('submit', (event) => {
      event.preventDefault();
      RUN.submitForm(ajaxForm, resetOnSubmit);
    });
  });

  // Handle tabs
  const tabContainers = a.querySelectorAll('.tabs');
  tabContainers.forEach((tabContainer) => {
    const { tabSelector } = tabContainer.dataset;
    tabContainer.addEventListener('click', event => handleTabs(event, tabContainer, tabSelector));
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
      display: show ? '' : 'none',
    });
  } else {
    if (!show) return;
    ol = document.createElement('div');
    ol.id = 'overlay';
    assign(ol.style, {
      height: `${dE.clientHeight}0px`,
      width: `${dE.clientWidth}0px`,
    });
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

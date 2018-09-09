(function () {
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

  const { userAgent } = navigator;

  ({
    chrome: !!userAgent.match(/chrome/i),
    ie: !!userAgent.match(/msie/i),
    iphone: !!userAgent.match(/iphone/i),
    mobile: !!userAgent.match(/mobile/i),
    n3ds: !!userAgent.match(/nintendo 3ds/),
    firefox: !!userAgent.match(/firefox/i),
    safari: !!userAgent.match(/safari/i),
  });

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

    load(url, {
      callback,
      data,
      method = this.setup.method,
      requestType = 1,
    } = {}) {
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

  /**
   * Selects/highlights all contents in an element
   * @param  {Element} element
   * @return {Void}
   */

  /**
   * If there's any highlighted text in element, replace it with content
   * @param {Element]} element
   * @param {String} content
   */
  function replaceSelection(element, content) {
    const scroll = element.scrollTop;
    if (document.selection) {
      element.focus();
      document.selection.createRange().text = content;
    } else {
      const s = element.selectionStart;
      const e = element.selectionEnd;
      element.value = element.value.substring(0, s) + content + element.value.substr(e);
      element.selectionStart = s + content.length;
      element.selectionEnd = s + content.length;
    }
    element.focus();
    element.scrollTop = scroll;
  }

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

  function parsetree(tree, prefix) {
    const nodes = Array.from(tree.querySelectorAll('li'));
    const order = {};
    let gotsomethin = 0;
    nodes.forEach((node) => {
      if (node.className !== 'seperator' && node.parentNode === tree) {
        gotsomethin = 1;
        const [sub] = node.getElementsByTagName('ul');
        order[`_${node.id.substr(prefix.length)}`] = sub !== undefined ? parsetree(sub, prefix) : 1;
      }
    });
    return gotsomethin ? order : 1;
  }

  function sortableTree (tree, prefix, formfield) {
    const listItems = Array.from(tree.querySelectorAll('li'));
    const items = [];
    const seperators = [];

    items.push(...listItems.filter(li => li.className !== 'title'));

    items.forEach((item) => {
      const tmp = document.createElement('li');
      tmp.className = 'seperator';
      seperators.push(tmp);
      insertBefore(tmp, item);
    });

    const drag = new Drag().noChildActivation();
    drag.drops(seperators.concat(items)).addListener({
      ondragover(a) {
        a.droptarget.style.border = '1px solid #000';
      },
      ondragout(a) {
        a.droptarget.style.border = 'none';
      },
      ondrop(a) {
        const next = a.droptarget.nextSibling;
        let tmp;
        const parentlock = a.el.className === 'parentlock';
        const nofirstlevel = a.el.className === 'nofirstlevel';
        if (a.droptarget) {
          a.droptarget.style.border = 'none';
        }
        if (a.droptarget.className === 'seperator') {
          if (parentlock && a.droptarget.parentNode !== a.el.parentNode) {
            return drag.reset(a.el);
          }
          if (nofirstlevel && a.droptarget.parentNode.className === 'tree') {
            return drag.reset(a.el);
          }
          if (isChildOf(a.droptarget, a.el) || a.el === next) {
            return drag.reset(a.el);
          }
          if (next.className === 'spacer') {
            next.parentNode.removeChild(next);
          }
          if (next.className !== 'spacer') {
            insertAfter(a.el.previousSibling, a.droptarget);
          } else {
            a.el.previousSibling.parentNode.removeChild(a.el.previousSibling);
          }
          insertAfter(a.el, a.droptarget);
        } else if (!parentlock && a.droptarget.tagName === 'LI') {
          [tmp] = a.droptarget.getElementsByTagName('ul');
          if (!tmp) {
            tmp = document.createElement('ul');
            a.droptarget.appendChild(tmp);
          }
          tmp.appendChild(a.el.previousSibling);
          tmp.appendChild(a.el);
          a.droptarget.appendChild(tmp);
        }
        drag.reset(a.el);
        if (formfield) {
          formfield.value = JSON.stringify(parsetree(tree, prefix));
        }
        return null;
      },
    });

    items.forEach(item => drag.apply(item));
  }

  const VALID_CLASS = 'valid';
  const INVALID_CLASS = 'invalid';

  function fetchResults(queryParams, el$$1, outputElement, event = {}) {
    const e = Event$1(event);
    el$$1.onkeydown = (event2) => {
      const e2 = Event$1(event2);
      if (e2.ENTER) {
        e2.cancel();
        return false;
      }
      return true;
    };
    let d = document.querySelector('#autocomplete');
    const coords = getCoordinates(el$$1);
    let els;
    let sindex = -1;
    let l = 0;
    if (!d) {
      d = document.createElement('div');
      d.id = 'autocomplete';
      d.style.position = 'absolute';
      d.style.zIndex = getHighestZIndex();
      document.querySelector('#page').appendChild(d);
    } else {
      d.style.display = '';
      els = Array.from(d.querySelectorAll('div'));
      l = els.length;
      sindex = els.findIndex(elmnt => elmnt.classList.contains('selected'));
    }
    d.style.top = `${coords.yh}px`;
    d.style.left = `${coords.x}px`;
    d.style.width = `${coords.w}px`;

    if (e.UP && l && sindex >= 1) {
      els[sindex].classList.remove('selected');
      els[sindex - 1].classList.add('selected');
    } else if (
      e.DOWN
      && l
      && (sindex < l - 1 || sindex >= -1)
    ) {
      if (sindex >= -1) {
        els[0].classList.add('selected');
      } else {
        els[sindex].classList.remove('selected');
        els[sindex + 1].classList.add('selected');
      }
    } else if (e.ENTER && l && sindex >= -1) {
      els[sindex].onclick();
    } else {
      const relativePath = document.location.toString().match('/acp/') ? '../' : '';
      new Ajax().load(
        `${relativePath}misc/listloader.php?${queryParams}`,
        {
          callback: (xml) => {
            const results = JSON.parse(xml.responseText);
            d.innerHTML = '';
            if (!results.length) {
              d.style.display = 'none';
            } else {
              const [ids, values] = results;
              ids.forEach((key, i) => {
                const value = values[i];
                const div = document.createElement('div');
                div.innerHTML = value;
                div.onclick = () => {
                  div.parentNode.style.display = 'none';
                  if (outputElement) {
                    outputElement.value = key;
                    outputElement.dispatchEvent(new Event('change'));
                  }
                  el$$1.value = value;
                };
                d.appendChild(div);
              });
            }
          },
        },
      );
    }
  }

  function decorateElement(element) {
    // Disable native autocomplete behavior
    element.autocomplete = 'off';
    const action = element.dataset.autocompleteAction;
    const output = element.dataset.autocompleteOutput;
    const indicator = element.dataset.autocompleteIndicator;
    const outputElement = output && document.querySelector(output);
    const indicatorElement = indicator && document.querySelector(indicator);

    if (indicatorElement && outputElement) {
      outputElement.addEventListener('change', () => {
        indicatorElement.classList.add(VALID_CLASS);
      });
    }
    element.addEventListener('keyup', (event) => {
      const searchTerm = encodeURIComponent(element.value);
      if (indicatorElement) {
        indicatorElement.classList.remove(VALID_CLASS);
        indicatorElement.classList.add(INVALID_CLASS);
      }
      fetchResults(`act=${action}&term=${searchTerm}`, element, outputElement, event);
    });
  }

  function dropdownMenu(e) {
    const el$$1 = e.target;
    if (el$$1.tagName.toLowerCase() === 'a') {
      const menu = document.querySelector(`#menu_${el$$1.classList[0]}`);
      el$$1.classList.add('active');
      const s = menu.style;
      s.display = 'block';
      const p = getCoordinates(el$$1);
      s.top = `${p.y + el$$1.clientHeight}px`;
      s.left = `${p.x}px`;
      el$$1.onmouseout = (e2) => {
        if (!e2.relatedTarget && e2.toElement) e2.relatedTarget = e2.toElement;
        if (e2.relatedTarget !== menu && e2.relatedTarget.offsetParent !== menu) {
          el$$1.classList.remove('active');
          menu.style.display = 'none';
        }
      };
      menu.onmouseout = (e2) => {
        if (!e2.relatedTarget && e2.toElement) e2.relatedTarget = e2.toElement;
        if (
          e2.relatedTarget !== el$$1
          && e2.relatedTarget.offsetParent !== menu
          && e2.relatedTarget !== menu
        ) {
          el$$1.classList.remove('active');
          menu.style.display = 'none';
        }
      };
    }
  }

  function submitForm(form) {
    const names = [];
    const values = [];
    const elements = Array.from(form.elements);
    const submit = form.submitButton;
    elements.forEach((element) => {
      if (!element.name || element.type === 'submit') return;
      if (
        (element.type === 'checkbox' || element.type === 'radio')
        && !element.checked
      ) {
        return;
      }
      names.push(element.name);
      values.push(element.value);
    });

    if (submit) {
      names.push(submit.name);
      values.push(submit.value);
    }
    new Ajax().load(document.location.search, { data: [names, values] });
    // eslint-disable-next-line no-alert
    alert("Saved. Ajax-submitted so you don't lose your place");
  }

  function gracefulDegrade() {
    // Dropdown menu
    document.querySelector('#nav').addEventListener('mouseover', dropdownMenu);

    Array.from(document.querySelectorAll('form[data-use-ajax-submit]')).forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitForm(form);
      });
    });

    // Converts all switches (checkboxes) into graphics, to show "X" or "check"
    const switches = Array.from(document.querySelectorAll('.switch'));
    switches.forEach((switchEl) => {
      const toggle = document.createElement('div');
      toggle.className = switchEl.className.replace('switch', 'switch_converted');
      switchEl.style.display = 'none';
      if (!switchEl.checked) {
        toggle.style.backgroundPosition = 'bottom';
      }
      toggle.addEventListener('click', () => {
        switchEl.checked = !switchEl.checked;
        toggle.style.backgroundPosition = switchEl.checked ? 'top' : 'bottom';
        switchEl.dispatchEvent(new Event('change'));
      });
      insertAfter(toggle, switchEl);
    });

    // Makes editors capable of tabbing for indenting
    const editor = document.querySelector('.editor');
    if (editor) {
      editor.addEventListener('keydown', (event) => {
        if (event.keyCode === 9) {
          replaceSelection(editor, '    ');
          event.preventDefault();
        }
      });
    }

    // Hook up autocomplete form fields
    const autoCompleteFields = document.querySelectorAll('[data-autocomplete-action]');
    autoCompleteFields.forEach((field) => {
      decorateElement(field);
    });

    // Orderable forums needs this
    const tree = document.querySelector('.tree');
    if (tree) {
      sortableTree(tree, 'forum_', document.querySelector('#ordered'));
    }
  }
  onDOMReady(gracefulDegrade);

}());

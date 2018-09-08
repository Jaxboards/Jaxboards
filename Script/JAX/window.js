import Animation from './animation';
import Drag from './drag';
import { getHighestZIndex } from './el';
import { assign, toggleOverlay, onImagesLoaded } from './util';

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

    // add close window functionality
    const close = () => this.close();
    windowContainer.querySelectorAll('[data-window-close]').forEach((closeElement) => {
      closeElement.addEventListener('click', close);
    });

    // Add the window to the document
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

/**
 * Given an element, attempt to find the window that the element is contained in and close it.
 * @static
 * @param  {Element} windowElementDescendant window element or child element of a window
 * @return {Void}
 */
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

export default Window;

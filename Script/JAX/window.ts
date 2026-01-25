import { animate } from "./animation";
import Drag, { DragSession } from "./drag";
import { getHighestZIndex } from "./el";
import { onImagesLoaded } from "./util";

export type WindowOptions = Partial<{
  title: string;
  content: string;
  id: string;

  animate: boolean;
  wait: boolean;
  open: boolean;
  minimizable: boolean;

  resize: string;
  className: string;
  pos: "center";
  zIndex: number;
}>;

class Window {
  title = "Title";

  wait = true;

  content = "Content";

  open = false;

  minimizable = true;

  resize?: string;

  className = "";

  pos = "center";

  zIndex: number;

  windowContainer?: HTMLDivElement;

  id?: string;

  onclose?: () => void;

  animate = false;

  private oldpos?: string;

  constructor(options: WindowOptions = {}) {
    this.zIndex = getHighestZIndex();

    Object.assign(this, options);

    this.createDom();
  }

  get element() {
    return this.windowContainer;
  }

  /**
   * Given an element, attempt to find the window that the element is contained in and close it.
   * @static
   * @param  {Element} windowElementDescendant window element or child element of a window
   * @return {Void}
   */
  static close(window: HTMLElement) {
    let element: HTMLElement | null = window;
    do {
      if ("close" in element && typeof element.close === "function") {
        element.close();
        break;
      }
      element = element.parentElement;
    } while (element);
  }

  private createDom() {
    this.windowContainer = document.createElement("div");
    const { windowContainer } = this;

    const titleBar = document.createElement("div");
    const contentContainer = document.createElement("div");
    const windowControls = document.createElement("div");
    const minimizeButton = document.createElement("div");
    const closeButton = document.createElement("div");
    const { pos } = this;

    if (this.id) {
      windowContainer.id = this.id;
    }

    windowContainer.className = `window${
      this.className ? ` ${this.className}` : ""
    }`;
    titleBar.className = "title";
    contentContainer.className = "content";
    if (this.minimizable) {
      minimizeButton.innerHTML = "-";
      minimizeButton.addEventListener("click", () => this.minimize());
    }
    closeButton.dataset.shortcut = "Escape";
    closeButton.innerHTML = "X";
    closeButton.addEventListener("click", () => this.close());
    windowControls.appendChild(minimizeButton);
    windowControls.appendChild(closeButton);
    windowControls.className = "controls";
    titleBar.innerHTML = this.title;
    contentContainer.innerHTML = this.content;
    titleBar.appendChild(windowControls);
    windowContainer.appendChild(titleBar);
    windowContainer.appendChild(contentContainer);

    // add close window functionality
    const close = () => this.close();
    windowContainer
      .querySelectorAll("[data-window-close]")
      .forEach((closeElement) => {
        closeElement.addEventListener("click", close);
      });

    // Add the window to the document
    document.body.appendChild(windowContainer);

    if (this.resize) {
      const targ: HTMLElement | null = windowContainer.querySelector(
        this.resize,
      );
      if (!targ) {
        throw new Error("Resize target not found");
      }
      targ.style.width = `${targ.clientWidth}px`;
      targ.style.height = `${targ.clientHeight}px`;
      const rsize = document.createElement("div");
      rsize.className = "resize";
      windowContainer.appendChild(rsize);
      rsize.style.left = `${windowContainer.clientWidth - 16}px`;
      rsize.style.top = `${windowContainer.clientHeight - 16}px`;
      new Drag()
        .boundingBox(100, 100, Infinity, Infinity)
        .addListener({
          ondrag(a: DragSession) {
            const w = Number.parseFloat(targ.style.width) + (a.dx ?? 0);
            const h = Number.parseFloat(targ.style.height) + (a.dy ?? 0);
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
    s.zIndex = `${this.zIndex + 5}`;

    if (this.wait) {
      void onImagesLoaded(
        Array.from(windowContainer.querySelectorAll("img")),
      ).then(() => {
        this.setPosition(pos);
      });
    } else this.setPosition(pos);

    new Drag()
      .autoZ()
      .noChildActivation()
      .boundingBox(
        0,
        0,
        document.documentElement.clientWidth - 50,
        document.documentElement.clientHeight - 50,
      )
      .apply(windowContainer, titleBar);
    return Object.assign(windowContainer, {
      close: () => this.close(),
      minimize: () => this.minimize(),
    });
  }

  close() {
    if (!this.windowContainer) {
      return;
    }
    this.windowContainer.remove();
    this.windowContainer = undefined;
    if (this.onclose) this.onclose();
  }

  minimize() {
    const c = this.windowContainer;
    if (!c) return;
    const isMinimized = c.classList.contains("minimized");
    c.classList.toggle("minimized");
    if (isMinimized) {
      c.removeAttribute("draggable");
      this.setPosition(this.oldpos ?? "");
    } else {
      c.setAttribute("draggable", "false");
      const wins = Array.from(document.querySelectorAll(".window"));
      const width = wins.reduce((w, window) => {
        if (window.classList.contains("minimized")) {
          return w + Number(window.clientWidth);
        }
        return w;
      }, 0);
      this.oldpos = this.getPosition();
      this.setPosition(`bl ${width} 0`, false);
    }
  }

  setPosition(pos: string, shouldAnimate = true) {
    const container = this.windowContainer;
    if (!container) return;
    let x = 0;
    let y = 0;
    const cH = document.documentElement.clientHeight;
    const cW = document.documentElement.clientWidth;
    const position = /(\d+) (\d+)/.exec(pos);
    if (position) {
      x = Number(position[1]);
      y = Number(position[2]);
    }
    x = Math.floor(x);
    y = Math.floor(y);
    if (pos.charAt(1) === "r") {
      x = cW - x - container.clientWidth;
    }
    switch (pos.charAt(0)) {
      case "b":
        y = cH - y - container.clientHeight;
        break;
      case "c":
        y = (cH - container.clientHeight) / 2;
        x = (cW - container.clientWidth) / 2;
        break;
      default:
    }
    x = Math.floor(x);
    y = Math.floor(y);

    if (x < 0) x = 0;
    if (y < 0) y = 0;
    container.style.left = `${x}px`;
    if (this.animate || shouldAnimate) {
      animate(
        container,
        [{ top: `${y - 100}px` }, { top: `${y}px` }],
        300,
        "ease-out",
      );
    } else container.style.top = `${y}px`;
    this.pos = pos;
  }

  getPosition(): string {
    if (!this.windowContainer) return "";
    const s = this.windowContainer.style;
    return `tl ${Number.parseFloat(s.left)} ${Number.parseFloat(s.top)}`;
  }
}

export default Window;

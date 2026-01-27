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

function toDOM<T extends HTMLElement>(html: string) {
  const div = document.createElement("div");
  div.innerHTML = html;
  return div.firstElementChild as T;
}

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

  animate = true;

  private oldpos?: string;

  constructor(options: WindowOptions = {}) {
    this.zIndex = getHighestZIndex();

    Object.assign(this, options);
  }

  public render() {
    const windowContainer = toDOM<HTMLDivElement>(`
      <div id="${this.id || ""}" class="window ${this.className}">
        <div class="title">
          ${this.title}
          <div class="controls">
            ${this.minimizable ? `<button data-action="minimize">-</button>` : ""}
            <button data-action="close" data-shortcut="Escape">X</button>
          </div>
        </div>
        <div class="content">${this.content}</div>
      </div>`);
    this.windowContainer = windowContainer;

    windowContainer.addEventListener("click", (event) => {
      if (event.target instanceof HTMLElement) {
        const action = event.target.dataset.action;
        if (action && action in this) {
          (this[action as keyof Window] as () => void)();
        }
      }
    });

    // Add the window to the document
    document.body.appendChild(windowContainer);

    if (this.resize) {
      this.renderResizeHandle(this.resize);
    }

    const s = windowContainer.style;
    s.zIndex = `${this.zIndex + 5}`;

    const { pos } = this;
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
      .apply(
        windowContainer,
        windowContainer.querySelector<HTMLDivElement>(".title") || undefined,
      );

    return windowContainer;
  }

  private renderResizeHandle(resizeTargetSelector: string) {
    const { windowContainer } = this;
    if (!windowContainer) {
      return;
    }
    const targ: HTMLElement | null =
      windowContainer.querySelector(resizeTargetSelector);
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
    if (this.animate && shouldAnimate) {
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

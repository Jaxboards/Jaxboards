/* global RUN, globalSettings */

import { addIdleClock } from "../components/idle-clock";
import { animate, dehighlight } from "../JAX/animation";
import { getCoordinates } from "../JAX/el";
import gracefulDegrade from "../JAX/graceful-degrade";
import { messageReceived } from "../JAX/instant-messaging-window";
import toast from "../JAX/toast";
import openTooltip from "../JAX/tooltip";
import { onImagesLoaded } from "../JAX/util";
import Window, { WindowOptions } from "../JAX/window";
import Sound from "../sound";

// Comes from UserOnline struct/class
type UserOnline = {
  birthday: boolean;
  groupID: number;
  hide: boolean;
  isBot: boolean;
  lastAction: number;
  lastUpdate: number;
  location: string;
  locationVerbose: string;
  name: string;
  profileURL: string | null;
  readDate: number;
  status: string;
  uid: number;
};

/**
 * These are all of the possible commands
 * that the server can send to the client.
 */
export default {
  loadscript(src: string) {
    document.body.appendChild(
      Object.assign(document.createElement("script"), { src }),
    );
  },
  script(script: string) {
    (0, eval)(script);
  },
  error(message: string) {
    toast.error(message);
  },
  success(message: string) {
    toast.success(message);
  },
  reload(timeout = 0) {
    setTimeout(() => globalThis.location.reload(), timeout);
  },
  refreshdata() {
    RUN.stream.pollData(true);
  },
  addclass(selector: string, className: string) {
    const el = document.querySelector(selector);
    if (el) {
      el.classList.add(className);
    }
  },
  title(title: string) {
    document.title = title;
  },
  update(sel: string, html: string, shouldHighlight: string) {
    let selector = sel;
    const paths = Array.from(document.querySelectorAll<HTMLElement>(".path"));
    if (selector === "path" && paths.length > 1) {
      paths.forEach((path) => {
        path.innerHTML = html;
        gracefulDegrade(path);
      });
      return;
    }
    if (!/^\W/.test(selector)) {
      selector = `#${selector}`;
    }
    const el = document.querySelector<HTMLElement>(selector);
    if (!el) return;
    el.innerHTML = html;

    const autofocusElement = el.querySelector<HTMLElement>("[autofocus]");
    if (autofocusElement) {
      autofocusElement.focus();
    }
    if (shouldHighlight) {
      dehighlight(el);
    }
    gracefulDegrade(el);
  },
  removeel(selector: string) {
    const el = document.querySelector(selector);
    if (el) el.remove();
  },
  back() {
    globalThis.history.back();
  },
  setstatus(className: string) {
    const status = document.querySelector("#status");
    if (status) {
      status.className = className;
    }
  },
  appendrows(selector: string, rowHTML: string) {
    const table = document.querySelector<HTMLTableElement>(selector);
    if (!table) return;
    const span = document.createElement("span");
    span.innerHTML = `<table>${rowHTML}</table>`;
    const vtbody = span.getElementsByTagName("tbody")[0];
    // table=table.getElementsByTagName('tbody')[0],
    gracefulDegrade(vtbody);
    table.appendChild(vtbody);
  },
  location(path: string) {
    if (["?", "/"].includes(path.charAt(0))) {
      RUN.stream.location(path);
      return;
    }
    document.location = path;
  },
  enable(selector: string) {
    const el = document.querySelector<HTMLButtonElement>(`#${selector}`);
    if (el) {
      el.disabled = false;
    }
  },
  addshout(message: string) {
    const shouts = Array.from(
      document.querySelectorAll<HTMLDivElement>("#shoutbox .shout"),
    );
    const span = document.createElement("span");
    span.innerHTML = message;
    const div = span.firstElementChild as HTMLDivElement;
    if (!div) return;
    shouts[0].parentNode?.insertBefore(div, shouts[0]);
    while (shouts.length > globalSettings.shoutLimit - 1) {
      shouts.pop()?.remove();
    }
    dehighlight(div);
    if (globalSettings.soundShout) {
      Sound.play("sbblip");
    }
    gracefulDegrade(div);
  },
  tick(html: string) {
    const ticker = document.querySelector("#ticker");
    if (!ticker) return;
    let tick = document.createElement("div");
    tick.innerHTML = html;
    tick = tick.firstElementChild as HTMLDivElement;

    ticker.insertBefore(tick, ticker.firstChild);
    const ticks = Array.from(ticker.querySelectorAll<HTMLDivElement>(".tick"));
    for (let x = 100; x < ticks.length; x += 100) {
      ticks[x].remove();
    }
  },
  im(
    fromId: number,
    fromName: string,
    message: string,
    fromMe: number,
    timestamp: number,
  ) {
    messageReceived({ fromId, fromName, message, fromMe, timestamp });
  },
  imtoggleoffline(a: string) {
    document.querySelector(`#im_${a}`)?.classList.add("offline");
  },
  window(options: WindowOptions) {
    const existingWindow = options.id && document.getElementById(options.id);

    if (existingWindow) {
      return;
    }
    const win = new Window(options);
    gracefulDegrade(win.render());
  },
  closewindow(windowSelector: string) {
    const el = document.querySelector<HTMLElement>(
      windowSelector + " [data-action=close]",
    );
    if (el) {
      el.click();
    }
  },
  onlinelist(users: UserOnline[]) {
    const statusers = document.querySelector("#statusers");
    if (!statusers) {
      return;
    }
    users.forEach((userOnline) => {
      let link = document.querySelector<HTMLAnchorElement>(
        `#statusers .user${userOnline.uid}`,
      );
      if (!link) {
        link = document.createElement("a");
      }
      link.innerHTML = userOnline.name;
      link.className = [
        `user${userOnline.uid}`,
        `mgroup${userOnline.groupID}`,
        userOnline.status,
        `lastAction${userOnline.lastAction}`,
      ].join(" ");

      if (userOnline.profileURL) {
        link.href = userOnline.profileURL;
        link.addEventListener("click", function click() {
          RUN.stream.location(this.href);
        });
      }
      if (userOnline.locationVerbose) {
        link.title = userOnline.locationVerbose;
        link.addEventListener("mouseover", () => openTooltip(link));
      }
      if (userOnline.status === "idle") {
        addIdleClock(link);

        return;
      }
      if (statusers.firstChild) {
        statusers.insertBefore(link, statusers.firstChild);
      } else statusers.appendChild(link);
    });
  },
  setoffline(userIds: string) {
    const statusers = document.querySelector("#statusers");
    const ids = userIds.split(",");
    ids.forEach((id) => {
      const link = document.querySelector(`#statusers .user${id}`);
      if (link && statusers) {
        link.remove();
      }
    });
  },

  async scrollToPost(postId: number) {
    const el = document.getElementById(`pid_${postId}`);
    if (!el) {
      return false;
    }
    await onImagesLoaded(Array.from(document.querySelectorAll("#page img")));
    const pos = getCoordinates(el);
    globalThis.scrollTo({ top: pos.y });
    return true;
  },
  updateqreply(content: string) {
    const qreply = document.querySelector("#qreply");
    const textarea = qreply?.querySelector("textarea");
    if (textarea) {
      textarea.focus();
      textarea.value += content;
    }
  },
  newmessage(message: string, fromMID: number) {
    let notification = document.querySelector<HTMLDivElement>("#notification");
    const num = document.querySelector<HTMLAnchorElement>("#num-messages");
    if (num) num.innerHTML = `${Number.parseInt(num.innerHTML, 10) + 1}`;
    if (!notification) {
      notification = document.createElement("div");
      notification.id = "notification";
      document.body.appendChild(notification);
    }
    notification.style.display = "";
    notification.className = "newmessage";
    notification.addEventListener("click", () => {
      notification.style.display = "none";
      RUN.stream.location(`/ucp/inbox?view=${fromMID}`, 3);
    });
    notification.innerHTML = message;
  },

  playsound(name: string, url: string) {
    Sound.loadAndPlay(name, url);
  },
  listrating(postId: number, html: string) {
    let prdiv = document.querySelector<HTMLDivElement>(`#postrating_${postId}`);
    let c;
    if (prdiv) {
      if (prdiv.style.display !== "none") {
        animate(prdiv, [{ height: "200px" }, { height: "0px" }], 300).then(
          (el) => (el.style.display = "none"),
        );
        return;
      }
      prdiv.style.display = "block";
    } else {
      prdiv = document.createElement("div");
      prdiv.className = "postrating_list";
      prdiv.id = `postrating_${postId}`;
      const postRating = document.querySelector<HTMLDivElement>(
        `#pid_${postId} .postrating`,
      );
      if (!postRating) {
        return;
      }
      c = getCoordinates(postRating);
      prdiv.style.top = `${c.yh}px`;
      prdiv.style.left = `${c.x}px`;
      document.querySelector<HTMLDivElement>("#page")?.appendChild(prdiv);
    }
    prdiv.innerHTML = html;
    animate(prdiv, [{ height: "0px" }, { height: "200px" }], 300);
  },
};

/* global RUN, globalSettings */

import { animate, dehighlight } from "../animation";
import { addIdleClock } from "../components/idle-clock";
import { getCoordinates, toDOM } from "../dom";
import createSnow, { stopSnow } from "../eggs/snow";
import gracefulDegrade from "../graceful-degrade";
import { messageReceived } from "../instant-messaging-window";
import Sound from "../sound";
import toast from "../toast";
import openTooltip from "../tooltip";
import { onImagesLoaded } from "../util";
import Window, { WindowOptions } from "../window";

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

const dom = {
  addclass(selector: string, className: string) {
    const el = document.querySelector(selector);
    if (el) {
      el.classList.add(className);
    }
  },
  enable(selector: string) {
    const el = document.querySelector<HTMLButtonElement>(`#${selector}`);
    if (el) {
      el.disabled = false;
    }
  },
  loadscript(src: string) {
    document.body.appendChild(
      Object.assign(document.createElement("script"), { src }),
    );
  },
  removeel(selector: string) {
    const el = document.querySelector(selector);
    if (el) el.remove();
  },
  setAttribute(selector: string, name: string, value: string) {
    const element = document.querySelector(selector);
    if (!element) return;
    if (value === "") {
      element.removeAttribute(name);
    } else {
      element.setAttribute(name, value);
    }
  },
  script(script: string) {
    (0, eval)(script);
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
};

const eggs = {
  snow(count: number) {
    createSnow(count);
  },
  confetti(count: number) {
    createSnow(count, true);
    setTimeout(stopSnow, 20_000);
  },
};

const instantMessaging = {
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
};

const inbox = {
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
};

const navigation = {
  back() {
    globalThis.history.back();
  },
  location(path: string) {
    if (["?", "/"].includes(path.charAt(0))) {
      RUN.stream.location(path);
      return;
    }
    document.location = path;
  },
  refreshdata() {
    RUN.stream.pollData(true);
  },
  reload(timeout = 0) {
    setTimeout(() => globalThis.location.reload(), timeout);
  },
  title(title: string) {
    document.title = title;
  },
};

const shoutbox = {
  addshout(message: string) {
    const shouts = Array.from(
      document.querySelectorAll<HTMLDivElement>("#shoutbox .shout"),
    );
    const div = toDOM<HTMLDivElement>(message);
    if (!div) return;
    shouts[0].parentNode?.insertBefore(div, shouts[0]);
    if (shouts.length > globalSettings.shoutLimit - 1) {
      shouts.pop()?.remove();
    }
    dehighlight(div);
    if (globalSettings.soundShout) {
      Sound.play("blip");
    }
    gracefulDegrade(div);
  },
  async expandShoutbox(title: string, shoutHTML: string) {
    const shoutboxTitle =
      document.querySelector<HTMLDivElement>("#shoutbox .title");
    const shoutboxShouts =
      document.querySelector<HTMLDivElement>("#shoutbox .shouts");

    if (!shoutboxTitle || !shoutboxShouts) return;

    shoutboxTitle.innerHTML = title;
    gracefulDegrade(shoutboxTitle);

    const offsetHeight = `${shoutboxShouts.offsetHeight}px`;
    Object.assign(shoutboxShouts.style, {
      overflow: "hidden",
    });
    shoutboxShouts.innerHTML = shoutHTML;
    gracefulDegrade(shoutboxShouts);

    await animate(shoutboxShouts, [
      { height: offsetHeight },
      { height: `${shoutboxShouts.scrollHeight}px` },
    ]);
    shoutboxShouts.removeAttribute("style");
  },
};

const topicView = {
  appendrows(selector: string, rowHTML: string) {
    const table = document.querySelector<HTMLTableElement>(selector);
    if (!table) return;
    const dom = toDOM<HTMLTableElement>(`<table>${rowHTML}</table>`);
    const vtbody = dom.querySelector("tbody");
    if (vtbody) {
      gracefulDegrade(vtbody);
      table.appendChild(vtbody);
    }
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
      textarea.dispatchEvent(new Event("input"));
    }
  },
};

const usersOnline = {
  onlinelist(users: UserOnline[]) {
    const statusers = document.querySelector<HTMLSpanElement>("#statusers");
    if (!statusers) {
      return;
    }
    users.forEach((userOnline) => {
      let link = document.querySelector<HTMLAnchorElement>(
        `#statusers .user${userOnline.uid}`,
      );

      if (!link) {
        link = document.createElement("a");

        link.addEventListener("click", function click() {
          if (this.href) RUN.stream.location(this.href);
        });
        link.addEventListener("mouseover", function mouseover() {
          openTooltip(this);
        });
      }

      if (userOnline.profileURL) {
        link.href = userOnline.profileURL;
      }
      link.innerHTML = userOnline.name;
      link.className = [
        `user${userOnline.uid}`,
        `mgroup${userOnline.groupID}`,
        userOnline.status,
        `lastAction${userOnline.lastAction}`,
      ].join(" ");

      if (userOnline.locationVerbose) {
        link.title = userOnline.locationVerbose;
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
    const statusers = document.querySelector<HTMLSpanElement>("#statusers");
    const ids = userIds.split(",");
    ids.forEach((id) => {
      const link = document.querySelector<HTMLAnchorElement>(
        `#statusers .user${id}`,
      );
      if (link && statusers) {
        link.remove();
      }
    });
  },
};

const windows = {
  window(options: WindowOptions) {
    const existingWindowContent = options.id
      ? document.querySelector<HTMLDivElement>(`#${options.id} .content`)
      : undefined;

    if (existingWindowContent) {
      existingWindowContent.innerHTML = options.content + "";
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
};

const sounds = {
  playsound(name: string) {
    Sound.loadAndPlay(name);
  },
};

const ticker = {
  tick(html: string) {
    const ticker = document.querySelector("#ticker");
    if (!ticker) return;
    const tick = toDOM<HTMLDivElement>(html);

    ticker.insertBefore(tick, ticker.firstChild);
    const ticks = Array.from(ticker.querySelectorAll<HTMLDivElement>(".tick"));
    for (let x = 100; x < ticks.length; x += 100) {
      ticks[x].remove();
    }
  },
};

const toasts = {
  error(message: string) {
    toast.error(message);
  },
  success(message: string) {
    toast.success(message);
  },
};

/**
 * These are all of the possible commands
 * that the server can send to the client.
 */
export default {
  ...dom,
  ...eggs,
  ...inbox,
  ...instantMessaging,
  ...navigation,
  ...shoutbox,
  ...sounds,
  ...ticker,
  ...toasts,
  ...topicView,
  ...usersOnline,
  ...windows,
};

import toast from "../JAX/toast";
import Commands from "./commands";

const UPDATE_INTERVAL = 5000;

export default class Stream {
  private readonly commands: typeof Commands;

  private timeout?: number;

  constructor() {
    this.commands = Commands;
  }

  get currentURL() {
    return `${document.location.pathname}${document.location.search}`;
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  handleRequestData(url: string, cmds: any[], requestType = 1) {
    let preventNavigation = false;
    cmds.forEach(([cmd, ...args]: [string, ...unknown[]]) => {
      if (cmd === "preventNavigation") {
        preventNavigation = true;
      } else if (cmd in this.commands) {
        // @ts-expect-error I tried for 30 minutes to fix this type error
        this.commands[cmd].apply(null, args);
      }
    });

    if (requestType >= 2) {
      if (!preventNavigation && url !== this.currentURL) {
        globalThis.history.pushState({ lastURL: url }, "", url);
        // pushstate is not a real browser event unfortunately, so I have to trigger it myself
        globalThis.dispatchEvent(new Event("pushstate"));
      }
    }
    this.pollData();
  }

  location(path: string, requestType = 2) {
    void this.load(path, { requestType });
  }

  async load(
    url: string,
    {
      body,
      method = "POST",
      requestType = 1,
    }: {
      body?: URLSearchParams;
      method?: string;
      requestType?: number;
    } = {},
  ) {
    try {
      const request = await fetch(url, {
        method,
        body,
        headers: {
          "X-JSACCESS": `${requestType}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
      });

      if (request.ok) {
        const json = (await request.json()) as [unknown[]];
        this.handleRequestData(url, json, requestType);
        return;
      }
      // server error
      toast.error(
        "An unrecoverable error has occurred.<br>Please try again later.",
      );
    } catch (_) {
      if (!navigator.onLine) {
        toast.error("You appear to be offline.");
      } else {
        toast.error("Network fetch failed: timeout");
      }
    }
  }

  pollData(isEager = false) {
    if (isEager) {
      void this.load(this.currentURL);
    }
    clearTimeout(this.timeout);
    if (document.cookie.includes(`actw=${window.name}`)) {
      this.timeout = setTimeout(
        () => this.load(this.currentURL),
        UPDATE_INTERVAL,
      );
    }
  }

  updatePage() {
    this.location(this.currentURL, 3);
  }
}

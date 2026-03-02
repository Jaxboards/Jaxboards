import toast from "../toast";
import Commands from "./commands";

const UPDATE_INTERVAL = 5000;

enum RequestType {
  UPDATING = 1,
  ACTING = 2,
  DIRECTLINK = 3,
}

export default class Stream {
  private readonly commands: typeof Commands;

  private timeout?: ReturnType<typeof setTimeout>;

  constructor() {
    this.commands = Commands;
  }

  get currentURL() {
    return `${document.location.pathname}${document.location.search}`;
  }

  handleRequestData(
    url: string,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    cmds: any[],
    requestType = RequestType.UPDATING,
  ) {
    let preventNavigation = false;
    cmds.forEach(([cmd, ...args]: [string, ...unknown[]]) => {
      if (cmd === "preventNavigation") {
        preventNavigation = true;
      } else if (cmd in this.commands) {
        // @ts-expect-error I tried for 30 minutes to fix this type error
        this.commands[cmd].apply(null, args);
      }
    });

    if (requestType >= RequestType.UPDATING) {
      if (!preventNavigation && url !== this.currentURL) {
        globalThis.history.pushState({ lastURL: url }, "", url);
        // pushstate is not a real browser event unfortunately, so I have to trigger it myself
        globalThis.dispatchEvent(new Event("pushstate"));
      }
    }
    this.pollData();
  }

  location(path: string, requestType = RequestType.ACTING) {
    void this.load(path, { requestType });
  }

  async load(
    url: string,
    {
      body,
      method = "POST",
      requestType = RequestType.UPDATING,
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
      // don't show errors for no-user-action update requests
      if (requestType === RequestType.UPDATING) {
        return;
      }

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
    this.location(this.currentURL, RequestType.DIRECTLINK);
  }
}

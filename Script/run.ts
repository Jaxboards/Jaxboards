import createSnow from "./eggs/snow";
import { stopTitleFlashing } from "./JAX/flashing-title";
import gracefulDegrade from "./JAX/graceful-degrade";
import { handleKeyboardShortcuts } from "./JAX/keyboard-shortcuts";
import { onDOMReady, supportsEmoji, updateDates } from "./JAX/util";
import Stream from "./RUN/stream";
import Sound from "./sound";

const useJSLinks = 2;

export class AppState {
  stream: Stream = new Stream();

  onAppReady() {
    if (useJSLinks) {
      gracefulDegrade(document.body);
    }

    // Add snow for Christmas
    const today = new Date();
    const isChristmas =
      today.getMonth() === 11 && [23, 24, 25].includes(today.getDate());
    if (isChristmas) {
      createSnow();
    }

    updateDates();
    setInterval(updateDates, 1000 * 30);

    this.stream.pollData();
    globalThis.addEventListener(
      "popstate",
      ({ state }: { state: { lastURL: string } }) => {
        if (state) {
          const { lastURL } = state;
          this.stream.updatePage(lastURL);
        } else {
          this.stream.updatePage(document.location.toString());
        }
      },
    );

    // Load sounds
    Sound.load("sbblip", "/Sounds/blip.mp3", false);
    Sound.load("imbeep", "/Sounds/receive.mp3", false);
    Sound.load("imnewwindow", "/Sounds/receive.mp3", false);
  }

  handleQuoting(link: HTMLAnchorElement) {
    const params = new URLSearchParams(link.search);
    const pid = params.get("quote");

    // If the user has selected text in the post, use it instead of the entire post
    if (pid) {
      const post = document.querySelector(`#pid_${pid} .post_content`);
      const selection = window.getSelection();
      if (post && selection && post.contains(selection.anchorNode)) {
        params.set("quotedText", selection.toString());
      }
    }

    params.set("qreply", document.querySelector("#qreply") ? "1" : "0");

    void this.stream.load(`${link.pathname}?${params.toString()}`);
  }

  setWindowActive() {
    document.cookie = `actw=${window.name}; SameSite:Lax`;
    stopTitleFlashing();
    this.stream.pollData();
  }
}

const RUN = new AppState();

onDOMReady(() => {
  RUN.onAppReady();
});
onDOMReady(() => {
  window.name = `${Math.random()}`;
  RUN.setWindowActive();
  globalThis.addEventListener("focus", () => {
    RUN.setWindowActive();
  });
});
onDOMReady(function featureDetectionClasses() {
  if (!supportsEmoji()) {
    document.documentElement.classList.add("no-emoji");
  }
  document.documentElement.classList.add("js-enabled");
});
onDOMReady(() => {
  globalThis.addEventListener("keyup", handleKeyboardShortcuts);
});

export default RUN;

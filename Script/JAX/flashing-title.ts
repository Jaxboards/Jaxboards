let flashInterval = -1;
let originalTitle = "";
let lastTitle = "";

export function stopTitleFlashing() {
  if (originalTitle) {
    document.title = originalTitle;
  }
  originalTitle = "";
  clearInterval(flashInterval);
}

export function flashTitle(title: string) {
  if (document.hasFocus()) {
    return;
  }
  stopTitleFlashing();
  if (!originalTitle) {
    originalTitle = document.title;
  }
  lastTitle = title;
  flashInterval = setInterval(() => {
    document.title =
      document.title === originalTitle ? lastTitle : originalTitle;
  }, 1000);
}

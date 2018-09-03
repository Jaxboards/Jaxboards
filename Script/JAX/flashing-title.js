// TODO: Create an instance for this state
// instead of abusing the module

let flashInterval;
let originalTitle = '';
let lastTitle = '';

export const flashTitle = function (title) {
  if (document.hasFocus()) {
    return;
  }
  stopTitleFlashing();
  if (originalTitle == '') {
    originalTitle = document.title;
  }
  lastTitle = title;
  flashInterval = setInterval(() => {
    document.title = document.title == originalTitle
      ? lastTitle
      : originalTitle;
  }, 1000);
};

export const stopTitleFlashing = function () {
  if (originalTitle) {
    document.title = originalTitle;
  }
  originalTitle = '';
  clearInterval(flashInterval);
};

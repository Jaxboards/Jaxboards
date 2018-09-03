export const getComputedStyle = function (a, b) {
  if (!a) return false;
  if (a.currentStyle) return a.currentStyle;
  if (window.getComputedStyle) return window.getComputedStyle(a, b);
  return false;
};

export const getCoordinates = function (a) {
  let x = 0;
  let y = 0;
  const h = parseInt(a.offsetHeight) || 0;
  const w = parseInt(a.offsetWidth) || 0;
  do {
    x += parseInt(a.offsetLeft) || 0;
    y += parseInt(a.offsetTop) || 0;
  } while ((a = a.offsetParent));
  return {
    x,
    y,
    yh: y + h,
    xw: x + w,
    w,
    h,
  };
};

export const isChildOf = function (a, b) {
  while ((a = a.parentNode)) if (a == b) return true;
  return false;
};

export const insertBefore = function (a, b) {
  if (a.parentNode) a.parentNode.removeChild(a);
  b.parentNode.insertBefore(a, b);
};

export const insertAfter = function (a, b) {
  if (a.parentNode) a.parentNode.removeChild(a);
  b.parentNode.insertBefore(a, b.nextSibling);
};

export const replace = function (a, b) {
  insertBefore(b, a);
  if (a.parentNode) a.parentNode.removeChild(a);
};

export const getHighestZIndex = function () {
  const a = document.getElementsByTagName('*');
  const l = a.length;
  let x;
  let max = 0;
  for (x = 0; x < l; x++) {
    if (a[x].style.zIndex && Number(a[x].style.zIndex) > max) {
      max = Number(a[x].style.zIndex);
    }
  }
  return max + 1;
};

export default {
  getComputedStyle,
  getCoordinates,
  isChildOf,
  insertBefore,
  insertAfter,
  replace,
  getHighestZIndex,
};

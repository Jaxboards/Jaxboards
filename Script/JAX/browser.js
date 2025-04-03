const { userAgent } = navigator;

export default {
  chrome: /chrome/i.test(userAgent),
  ie: /msie/i.test(userAgent),
  iphone: /iphone/i.test(userAgent),
  mobile: /mobile/i.test(userAgent),
  n3ds: /nintendo 3ds/.test(userAgent),
  firefox: /firefox/i.test(userAgent),
  safari: /safari/i.test(userAgent),
};

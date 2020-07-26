const { userAgent } = navigator;

export default {
  chrome: !!userAgent.match(/chrome/i),
  ie: !!userAgent.match(/msie/i),
  iphone: !!userAgent.match(/iphone/i),
  mobile: !!userAgent.match(/mobile/i),
  n3ds: !!userAgent.match(/nintendo 3ds/),
  firefox: !!userAgent.match(/firefox/i),
  safari: !!userAgent.match(/safari/i),
};

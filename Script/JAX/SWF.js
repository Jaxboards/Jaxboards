import Browser from './browser';

export default function(url, name, settings) {
  var object;
  var embed;
  var x;
  var s = {
    width: "100%",
    height: "100%",
    quality: "high"
  };
  for (x in settings) s[x] = settings[x];
  object =
    '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="' +
    name +
    '" width="' +
    s.width +
    '" height="' +
    s.height +
    '"><param name="movie" value="' +
    url +
    '"></param>';
  embed =
    '<embed style="display:block" type="application/x-shockwave-flash" pluginspage="https://get.adobe.com/flashplayer/" src="' +
    url +
    '" width="' +
    s.width +
    '" height="' +
    s.height +
    '" name="' +
    name +
    '"';
  for (x in s) {
    if (x != "width" && x != "height") {
      object += '<param name="' + x + '" value="' + s[x] + '"></param>';
      embed += " " + x + '="' + s[x] + '"';
    }
  }
  embed += "></embed>";
  object += "</object>";
  var tmp = document.createElement("span");
  tmp.innerHTML = Browser.ie ? object : embed;
  return tmp.getElementsByTagName("*")[0];
};
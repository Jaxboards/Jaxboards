import Browser from './browser';

export default function (url, name, settings) {
  let object;
  let embed;
  const properties = {
    width: '100%',
    height: '100%',
    quality: 'high',
    ...settings,
  };
  object = `<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="${
    name
  }" width="${
    properties.width
  }" height="${
    properties.height
  }"><param name="movie" value="${
    url
  }"></param>`;
  embed = `<embed style="display:block" type="application/x-shockwave-flash" pluginspage="https://get.adobe.com/flashplayer/" src="${
    url
  }" width="${
    properties.width
  }" height="${
    properties.height
  }" name="${
    name
  }"`;

  Object.keys(properties).forEach((key) => {
    const value = properties[key];
    if (key !== 'width' && key !== 'height') {
      object += `<param name="${key}" value="${value}"></param>`;
      embed += ` ${key}="${value}"`;
    }
  });

  embed += '></embed>';
  object += '</object>';
  const tmp = document.createElement('span');
  tmp.innerHTML = Browser.ie ? object : embed;
  return tmp.getElementsByTagName('*')[0];
}

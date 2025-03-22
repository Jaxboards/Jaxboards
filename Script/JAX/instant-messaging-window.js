/* global RUN,globalsettings */
import Event from './event';
import { getHighestZIndex, isChildOf } from './el';

class IMWindow {
  constructor(uid, uname) {
    if (!globalsettings.can_im) {
      // eslint-disable-next-line no-alert
      alert('You do not have permission to use this feature.');
    } else {
      RUN.stream.commands.im([uid, uname, false]);
    }
  }
}

IMWindow.menu = function openMenu(event, uid) {
  const e = Event(event).stopBubbling();
  const d = document.createElement('div');
  d.innerHTML = 'loading';
  d.style.position = 'absolute';
  d.style.left = `${e.pageX}px`;
  d.style.top = `${e.pageY}px`;
  d.style.zIndex = getHighestZIndex();
  d.id = 'immenu';
  d.className = 'immenu';
  document.body.appendChild(d);
  document.body.onclick = (clickEvent) => {
    const ce = Event(clickEvent);
    if (ce.srcElement !== d && !isChildOf(ce.srcElement, d)) {
      d.parentNode.removeChild(d);
    }
  };

  RUN.stream.load(`?module=privatemessage&im_menu=${uid}`);
};

export default IMWindow;

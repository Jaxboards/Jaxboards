import Ajax from './ajax';
import Event from './event';
import { getHighestZIndex, getCoordinates } from './el';

export default function (a, el, dummy, e) {
  if (e) e = Event(e);
  else e = {};
  el.onkeydown = function (e) {
    e = Event(e);
    if (e.ENTER) {
      e.cancel();
      return false;
    }
  };
  let d = document.querySelector('#autocomplete');
  const coords = getCoordinates(el);
  let els;
  let sindex;
  let x;
  let l = 0;
  if (!d) {
    d = document.createElement('div');
    d.id = 'autocomplete';
    d.style.position = 'absolute';
    d.style.zIndex = getHighestZIndex();
    document.querySelector('#page').appendChild(d);
  } else {
    d.style.display = '';
    els = d.getElementsByTagName('div');
    l = els.length || 0;
    for (x = 0; x < l; x++) {
      if (els[x].classList.contains('selected')) {
        sindex = x;
        break;
      }
    }
  }
  d.style.top = `${coords.yh}px`;
  d.style.left = `${coords.x}px`;
  d.style.width = `${coords.w}px`;
  if (e.UP && l && sindex >= 1 && typeof sindex !== 'undefined') {
    els[sindex].classList.remove('selected');
    els[sindex - 1].classList.add('selected');
  } else if (
    e.DOWN
    && l
    && (sindex < l - 1 || typeof sindex === 'undefined')
  ) {
    if (typeof sindex === 'undefined') {
      els[0].classList.add('selected');
    } else {
      els[sindex].classList.remove('selected');
      els[sindex + 1].classList.add('selected');
    }
  } else if (e.ENTER && l && typeof sindex !== undefined) {
    els[sindex].onclick();
  } else {
    var a = new Ajax().load(
      `${document.location.toString().match('/acp/') ? '../' : ''
      }misc/listloader.php?${
        a}`,
      (xml) => {
        let x;
        let tmp;
        xml = eval(xml.responseText);
        d.innerHTML = '';
        for (x = 0; x < xml[0].length; x++) {
          tmp = document.createElement('div');
          tmp.innerHTML = xml[1][x];
          tmp.key = xml[0][x];
          tmp.onclick = function () {
            this.parentNode.style.display = 'none';
            if (dummy) {
              dummy.value = this.key;
              if (dummy.onchange) dummy.onchange();
            }
            el.value = this.innerHTML;
          };
          d.appendChild(tmp);
        }
        if (!xml[0].length) d.style.display = 'none';
      },
    );
  }
}

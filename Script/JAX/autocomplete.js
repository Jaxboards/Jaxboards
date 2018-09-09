import Ajax from './ajax';
import JaxEvent from './event';
import { getHighestZIndex, getCoordinates } from './el';

const VALID_CLASS = 'valid';
const INVALID_CLASS = 'invalid';

function fetchResults(queryParams, el, outputElement, event = {}) {
  const e = JaxEvent(event);
  el.onkeydown = (event2) => {
    const e2 = JaxEvent(event2);
    if (e2.ENTER) {
      e2.cancel();
      return false;
    }
    return true;
  };
  let d = document.querySelector('#autocomplete');
  const coords = getCoordinates(el);
  let els;
  let sindex = -1;
  let l = 0;
  if (!d) {
    d = document.createElement('div');
    d.id = 'autocomplete';
    d.style.position = 'absolute';
    d.style.zIndex = getHighestZIndex();
    document.querySelector('#page').appendChild(d);
  } else {
    d.style.display = '';
    els = Array.from(d.querySelectorAll('div'));
    l = els.length;
    sindex = els.findIndex(elmnt => elmnt.classList.contains('selected'));
  }
  d.style.top = `${coords.yh}px`;
  d.style.left = `${coords.x}px`;
  d.style.width = `${coords.w}px`;

  if (e.UP && l && sindex >= 1) {
    els[sindex].classList.remove('selected');
    els[sindex - 1].classList.add('selected');
  } else if (
    e.DOWN
    && l
    && (sindex < l - 1 || sindex >= -1)
  ) {
    if (sindex >= -1) {
      els[0].classList.add('selected');
    } else {
      els[sindex].classList.remove('selected');
      els[sindex + 1].classList.add('selected');
    }
  } else if (e.ENTER && l && sindex >= -1) {
    els[sindex].onclick();
  } else {
    const relativePath = document.location.toString().match('/acp/') ? '../' : '';
    new Ajax().load(
      `${relativePath}misc/listloader.php?${queryParams}`,
      {
        callback: (xml) => {
          const results = JSON.parse(xml.responseText);
          d.innerHTML = '';
          if (!results.length) {
            d.style.display = 'none';
          } else {
            const [ids, values] = results;
            ids.forEach((key, i) => {
              const value = values[i];
              const div = document.createElement('div');
              div.innerHTML = value;
              div.onclick = () => {
                div.parentNode.style.display = 'none';
                if (outputElement) {
                  outputElement.value = key;
                  outputElement.dispatchEvent(new Event('change'));
                }
                el.value = value;
              };
              d.appendChild(div);
            });
          }
        },
      },
    );
  }
}

export default function decorateElement(element) {
  // Disable native autocomplete behavior
  element.autocomplete = 'off';
  const action = element.dataset.autocompleteAction;
  const output = element.dataset.autocompleteOutput;
  const indicator = element.dataset.autocompleteIndicator;
  const outputElement = output && document.querySelector(output);
  const indicatorElement = indicator && document.querySelector(indicator);

  if (indicatorElement && outputElement) {
    outputElement.addEventListener('change', () => {
      indicatorElement.classList.add(VALID_CLASS);
    });
  }
  element.addEventListener('keyup', (event) => {
    const searchTerm = encodeURIComponent(element.value);
    if (indicatorElement) {
      indicatorElement.classList.remove(VALID_CLASS);
      indicatorElement.classList.add(INVALID_CLASS);
    }
    fetchResults(`act=${action}&term=${searchTerm}`, element, outputElement, event);
  });
}

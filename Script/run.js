import {
  gracefulDegrade,
  updateDates,
  onDOMReady,
} from './JAX/util';
import {
  smalldate,
} from './JAX/date';
import {
  stopTitleFlashing,
} from './JAX/flashing-title';
import Stream from './RUN/stream';
import Sound from './sound';

const updatetime = 5000;
const useJSLinks = 2;

/* Returns the path to this script. */
function getJXBDBaseDir() {
  const scripts = Array.from(document.querySelectorAll('script'));
  const found = scripts
    .find(script => script.src.substr(script.src.length - 8, 8) === 'run.js');
  if (found) {
    return found.src.substr(0, found.src.length - 8);
  }
  return null;
}

class AppState {
  onAppReady() {
    this.stream = new Stream();

    if (useJSLinks) {
      gracefulDegrade(document.body);
    }

    updateDates();
    setInterval(updateDates, 1000 * 30);

    this.nextDataPoll();
    setInterval(() => this.stream.updatePage, 200);

    if (useJSLinks && document.location.toString().indexOf('?') > 0) {
      const hash = `#${document.location.search.substr(1)}`;
      if (useJSLinks === 2) {
        window.history.replaceState({}, '', `./${hash}`);
      } else {
        document.location = hash;
      }
    }


    // Load sounds
    const basedir = getJXBDBaseDir();
    Sound.load('sbblip', `${basedir}Sounds/blip.mp3`, false);
    Sound.load('imbeep', `${basedir}Sounds/receive.mp3`, false);
    Sound.load('imnewwindow', `${basedir}Sounds/receive.mp3`, false);

    document.cookie = 'buddylist=0';
  }

  submitForm(form, clearFormOnSubmit = false) {
    const names = [];
    const values = [];
    const submit = form.submitButton;

    form.elements.forEach((inputField) => {
      if (!inputField.name || inputField.type === 'submit') {
        return;
      }

      if (inputField.type === 'select-multiple') {
        inputField.options
          .filter(option => option.selected)
          .forEach((option) => {
            names.push(`${inputField.name}[]`);
            values.push(option.value);
          });
        return;
      }

      if ((inputField.type === 'checkbox' || inputField.type === 'radio') && !inputField.checked) {
        return;
      }
      names.push(inputField.name);
      values.push(inputField.value);
    });

    if (submit) {
      names.push(submit.name);
      values.push(submit.value);
    }
    RUN.stream.load('?', 0, [names, values], 1, 1);
    if (clearFormOnSubmit) {
      form.reset();
    }
    this.nextDataPoll();
    return false;
  }

  handleQuoting(a) {
    this.stream.load(`${a.href}&qreply=${document.querySelector('#qreply') ? '1' : '0'}`);
  }

  setWindowActive() {
    document.cookie = `actw=${window.name}`;
    stopTitleFlashing();
    this.nextDataPoll();
  }

  nextDataPoll(a) {
    const { stream } = this;
    if (a) {
      stream.loader();
    }
    clearTimeout(stream.timeout);
    if (document.cookie.match(`actw=${window.name}`)) {
      stream.timeout = setTimeout(stream.loader, updatetime);
    }
  }
}

const RUN = new AppState();

onDOMReady(() => {
  RUN.onAppReady();
});
onDOMReady(() => {
  window.name = Math.random();
  window.addEventListener('onfocus', () => {
    RUN.setWindowActive();
  });
});

export default RUN;

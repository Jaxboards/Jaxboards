import { gracefulDegrade, updateDates, onDOMReady } from './JAX/util';
import { stopTitleFlashing } from './JAX/flashing-title';
import Stream from './RUN/stream';
import Sound from './sound';

const useJSLinks = 2;

class AppState {
  onAppReady() {
    this.stream = new Stream();

    if (useJSLinks) {
      gracefulDegrade(document.body);
    }

    updateDates();
    setInterval(updateDates, 1000 * 30);

    this.stream.pollData();
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
    Sound.load('sbblip', './Sounds/blip.mp3', false);
    Sound.load('imbeep', './Sounds/receive.mp3', false);
    Sound.load('imnewwindow', './Sounds/receive.mp3', false);

    document.cookie = 'buddylist=0';
  }

  submitForm(form, resetOnSubmit = false) {
    const names = [];
    const values = [];
    const submit = form.submitButton;

    Array.from(form.elements).forEach((inputField) => {
      if (!inputField.name || inputField.type === 'submit') {
        return;
      }

      if (inputField.type === 'select-multiple') {
        Array.from(inputField.options)
          .filter(option => option.selected)
          .forEach((option) => {
            names.push(`${inputField.name}[]`);
            values.push(option.value);
          });
        return;
      }

      if (
        (inputField.type === 'checkbox' || inputField.type === 'radio')
        && !inputField.checked
      ) {
        return;
      }
      names.push(inputField.name);
      values.push(inputField.value);
    });

    if (submit) {
      names.push(submit.name);
      values.push(submit.value);
    }
    this.stream.load('?', { data: [names, values] });
    if (resetOnSubmit) {
      form.reset();
    }
    this.stream.pollData();
  }

  handleQuoting(a) {
    this.stream.load(
      `${a.href}&qreply=${document.querySelector('#qreply') ? '1' : '0'}`,
    );
  }

  setWindowActive() {
    document.cookie = `actw=${window.name}`;
    stopTitleFlashing();
    this.stream.pollData();
  }
}

const RUN = new AppState();

onDOMReady(() => {
  RUN.onAppReady();
});
onDOMReady(() => {
  window.name = Math.random();
  RUN.setWindowActive();
  window.addEventListener('onfocus', () => {
    RUN.setWindowActive();
  });
});

export default RUN;

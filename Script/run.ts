import { updateDates, onDOMReady } from './JAX/util';
import gracefulDegrade from './JAX/graceful-degrade';
import { stopTitleFlashing } from './JAX/flashing-title';
import Stream from './RUN/stream';
import Sound from './sound';

const useJSLinks = 2;

class AppState {
    stream: Stream = new Stream();

    onAppReady() {
        if (useJSLinks) {
            gracefulDegrade(document.body);
        }

        updateDates();
        setInterval(updateDates, 1000 * 30);

        this.stream.pollData();
        window.addEventListener('popstate', ({ state }) => {
            if (state) {
                const { queryParams } = state;
                this.stream.updatePage(queryParams);
            } else {
                const queryParams = document.location.search.replace(/^\?/, '');
                this.stream.updatePage(queryParams);
            }
        });

        // Load sounds
        Sound.load('sbblip', './Sounds/blip.mp3', false);
        Sound.load('imbeep', './Sounds/receive.mp3', false);
        Sound.load('imnewwindow', './Sounds/receive.mp3', false);
    }

    submitForm(form: HTMLFormElement, resetOnSubmit = false) {
        const names = [];
        const values = [];
        const { submitButton } = form;

        [
            ...Array.from(form.querySelectorAll('input')),
            ...Array.from(form.querySelectorAll('select')),
            ...Array.from(form.querySelectorAll('button')),
        ].forEach((inputField) => {
            if (!inputField.name || inputField.type === 'submit') {
                return;
            }

            if (inputField instanceof HTMLSelectElement && inputField.type === 'select-multiple') {
                Array.from(inputField.options)
                    .filter((option) => option.selected)
                    .forEach((option) => {
                        names.push(`${inputField.name}[]`);
                        values.push(option.value);
                    });
                return;
            }

            if (
                inputField instanceof HTMLInputElement &&
                ['checkbox', 'radio'].includes(inputField.type) &&
                !inputField.checked
            ) {
                return;
            }
            names.push(inputField.name);
            values.push(inputField.value);
        });

        if (submitButton) {
            names.push(submitButton.name);
            values.push(submitButton.value);
        }
        this.stream.load('?', { data: [names, values] });
        if (resetOnSubmit) {
            form.reset();
        }
        this.stream.pollData();
    }

    handleQuoting(link: HTMLLinkElement) {
        this.stream.load(
            `${link.href}&qreply=${document.querySelector('#qreply') ? '1' : '0'}`,
        );
    }

    setWindowActive() {
        document.cookie = `actw=${window.name}; SameSite:Lax`;
        stopTitleFlashing();
        this.stream.pollData();
    }
}

const RUN = new AppState();

onDOMReady(() => {
    RUN.onAppReady();
});
onDOMReady(() => {
    window.name = `${Math.random()}`;
    RUN.setWindowActive();
    window.addEventListener('focus', () => {
        RUN.setWindowActive();
    });
});

export default RUN;

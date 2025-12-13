import { stopTitleFlashing } from './JAX/flashing-title';
import gracefulDegrade from './JAX/graceful-degrade';
import { onDOMReady, supportsEmoji, updateDates } from './JAX/util';
import Stream from './RUN/stream';
import Sound from './sound';

const useJSLinks = 2;

export class AppState {
    stream: Stream = new Stream();

    onAppReady() {
        if (useJSLinks) {
            gracefulDegrade(document.body);
        }

        updateDates();
        setInterval(updateDates, 1000 * 30);

        this.stream.pollData();
        globalThis.addEventListener('popstate', ({ state }) => {
            if (state) {
                const { lastURL } = state;
                this.stream.updatePage(lastURL);
            } else {
                this.stream.updatePage(document.location);
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

        const inputFields = ['input', 'select', 'button', 'textarea'] as const;

        inputFields
            .flatMap((tagName) => Array.from(form.querySelectorAll(tagName)))
            .forEach((inputField) => {
                if (!inputField.name || inputField.type === 'submit') {
                    return;
                }

                if (
                    inputField instanceof HTMLSelectElement &&
                    inputField.type === 'select-multiple'
                ) {
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
        this.stream.load(form.action || globalThis.location, {
            data: [names, values],
        });
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
        document.cookie = `actw=${globalThis.name}; SameSite:Lax`;
        stopTitleFlashing();
        this.stream.pollData();
    }
}

const RUN = new AppState();

onDOMReady(() => {
    RUN.onAppReady();
});
onDOMReady(() => {
    globalThis.name = `${Math.random()}`;
    RUN.setWindowActive();
    globalThis.addEventListener('focus', () => {
        RUN.setWindowActive();
    });
    if (!supportsEmoji()) {
        document.documentElement.classList.add('no-emoji');
    }
});

export default RUN;

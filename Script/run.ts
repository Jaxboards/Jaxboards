import createSnow from './eggs/snow';
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

        // Add snow for Christmas
        const today = new Date();
        const isChristmas =
            today.getMonth() === 11 && [23, 24, 25].includes(today.getDate());
        if (isChristmas) {
            createSnow();
        }

        updateDates();
        setInterval(updateDates, 1000 * 30);

        this.stream.pollData();
        globalThis.addEventListener('popstate', ({ state }) => {
            if (state) {
                const { lastURL } = state;
                this.stream.updatePage(lastURL);
            } else {
                this.stream.updatePage(document.location.toString());
            }
        });

        // Load sounds
        Sound.load('sbblip', '/Sounds/blip.mp3', false);
        Sound.load('imbeep', '/Sounds/receive.mp3', false);
        Sound.load('imnewwindow', '/Sounds/receive.mp3', false);
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
    globalThis.addEventListener('focus', () => {
        RUN.setWindowActive();
    });
    if (!supportsEmoji()) {
        document.documentElement.classList.add('no-emoji');
    }
});

export default RUN;

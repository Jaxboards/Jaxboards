import Window from '../JAX/window';

export default class MediaPlayer {
    element: HTMLDivElement;

    static selector(container: HTMLElement): void {
        container
            .querySelectorAll<HTMLDivElement>('.media')
            .forEach((el) => new this(el));
    }

    constructor(element: HTMLDivElement) {
        this.element = element;

        const popoutLink = element.querySelector<HTMLAnchorElement>('a.popout');
        const inlineLink = element.querySelector<HTMLAnchorElement>('a.inline');
        const movie = element.querySelector<HTMLDivElement>('.movie');

        if (!movie) return;

        popoutLink?.addEventListener('click', (event) => {
            event.preventDefault();
            const win = new Window({
                title: popoutLink.href,
                content: movie.innerHTML,
            });
            win.create();
        });

        inlineLink?.addEventListener('click', (event) => {
            event.preventDefault();
            movie.style.display = 'block';
        });
    }
}

import Component from '../classes/component';
import Window from '../JAX/window';

export default class MediaPlayer extends Component {
    static get selector() {
        return '.media';
    }

    constructor(element) {
        super(element);

        const popoutLink = element.querySelector('a.popout');
        const inlineLink = element.querySelector('a.inline');
        const movie = element.querySelector('.movie');

        popoutLink.addEventListener('click', (event) => {
            event.preventDefault();
            const win = new Window({
                title: popoutLink.href,
                content: movie.innerHTML,
            });
            win.create();
        });

        inlineLink.addEventListener('click', (event) => {
            event.preventDefault();
            movie.style.display = 'block';
        });
    }
}

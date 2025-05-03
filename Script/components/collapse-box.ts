import Animation from '../JAX/animation';
import Component from '../classes/component';

export default class CollapseBox extends Component {
    static get selector() {
        return '.collapse-box';
    }

    constructor(element) {
        super(element);

        element
            .querySelector('.collapse-button')
            .addEventListener('click', () => this.click());
    }

    click() {
        const collapseContent = this.element.querySelector('.collapse-content');

        const s = collapseContent.style;
        let fh = collapseContent.dataset.fullHeight;
        const b = collapseContent.parentNode;
        s.overflow = 'hidden';
        if (s.height === '0px') {
            new Animation(collapseContent, 5, 10, 0)
                .add('height', '0px', fh)
                .then(() => {
                    b.classList.remove('collapsed');
                })
                .play();
        } else {
            if (!fh) {
                fh = `${
                    collapseContent.clientHeight || collapseContent.offsetHeight
                }px`;
                collapseContent.dataset.fullHeight = fh;
            }
            new Animation(collapseContent, 5, 10, 0)
                .add('height', fh, '0px')
                .then(() => {
                    b.classList.add('collapsed');
                })
                .play();
        }
    }
}

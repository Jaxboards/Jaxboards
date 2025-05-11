import Animation from '../JAX/animation';
import Component from '../classes/component';

export default class CollapseBox extends Component {
    static get selector() {
        return '.collapse-box';
    }

    constructor(element: HTMLDivElement) {
        super(element);

        element
            .querySelector('.collapse-button')
            ?.addEventListener('click', () => this.click());
    }

    click() {
        const collapseContent =
            this.element.querySelector<HTMLDivElement>('.collapse-content');

        if (!collapseContent) return;

        const style = collapseContent.style;
        let fullHeight = collapseContent.dataset.fullHeight;
        const collapseBox = this.element;
        style.overflow = 'hidden';
        if (style.height === '0px' && fullHeight) {
            new Animation(collapseContent, 5, 10, 0)
                .add('height', '0px', fullHeight)
                .then(() => {
                    collapseBox.classList.remove('collapsed');
                })
                .play();
        } else {
            if (!fullHeight) {
                fullHeight = `${
                    collapseContent.clientHeight || collapseContent.offsetHeight
                }px`;
                collapseContent.dataset.fullHeight = fullHeight;
            }
            new Animation(collapseContent, 5, 10, 0)
                .add('height', fullHeight, '0px')
                .then(() => {
                    collapseBox.classList.add('collapsed');
                })
                .play();
        }
    }
}

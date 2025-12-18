import Animation from '../JAX/animation';
import register, { Component } from '../JAX/component';

export default class CollapseBox extends Component<HTMLDivElement> {
    static hydrate(container: HTMLElement): void {
        register(
            'CollapseBox',
            container.querySelectorAll<HTMLDivElement>('.collapse-box'),
            this,
        );
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

        const { style } = collapseContent;
        let { fullHeight } = collapseContent.dataset;
        const collapseBox = this.element;
        style.overflow = 'hidden';
        if (style.height === '0px' && fullHeight) {
            new Animation(collapseContent, 5, 10, 0)
                .add('height', '0px', fullHeight)
                .andThen(() => {
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
                .andThen(() => {
                    collapseBox.classList.add('collapsed');
                })
                .play();
        }
    }
}

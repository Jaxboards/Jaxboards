import Animation from '../JAX/animation';

export default class CollapseBox {
    element: HTMLDivElement;

    static selector(container: HTMLElement): void {
        container
            .querySelectorAll<HTMLDivElement>('.collapse-box')
            .forEach((el) => new this(el));
    }

    constructor(element: HTMLDivElement) {
        this.element = element;

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

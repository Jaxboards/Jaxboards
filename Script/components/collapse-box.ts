import register, { Component } from '../JAX/component';

export default class CollapseBox extends Component<HTMLDivElement> {
    private readonly fullHeight: number = 0;

    static hydrate(container: HTMLElement): void {
        register(
            'CollapseBox',
            container.querySelectorAll<HTMLDivElement>('.collapse-box'),
            this,
        );
    }

    constructor(element: HTMLDivElement) {
        super(element);

        // Set up initial height
        const { collapseContent } = this;
        if (collapseContent) {
            this.fullHeight =
                collapseContent.clientHeight || collapseContent.offsetHeight;

            Object.assign(collapseContent.style, {
                height: `${this.fullHeight}px`,
                transition: 'height 200ms ease-in-out',
                overflow: 'hidden',
            });
        }

        this.element.addEventListener('click', (event) => {
            if (
                event.target instanceof HTMLElement &&
                event.target.matches('.collapse-button')
            ) {
                this.toggle();
            }
        });
    }

    get collapseContent() {
        return this.element.querySelector<HTMLDivElement>('.collapse-content');
    }

    toggle() {
        const { collapseContent } = this;

        if (!collapseContent) return;

        const { style } = collapseContent;

        style.height = `${style.height === '0px' ? this.fullHeight : 0}px`;
    }
}

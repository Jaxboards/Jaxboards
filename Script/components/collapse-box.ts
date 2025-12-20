import register, { Component } from '../JAX/component';

export default class CollapseBox extends Component<HTMLDivElement> {
    private fullHeight: number = 0;

    private animationLengthInMs: number = 200;

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
            Object.assign(collapseContent.style, {
                transition: `height ${this.animationLengthInMs}ms ease-in-out`,
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

        if (style.height === '0px') {
            style.height = `${this.fullHeight}px`;
            setTimeout(() => {
                style.removeProperty('height');
                style.removeProperty('overflow');
            }, this.animationLengthInMs);
        } else {
            this.fullHeight =
                collapseContent.clientHeight || collapseContent.offsetHeight;
            style.height = `${this.fullHeight}px`;
            style.overflow = 'hidden';
            requestAnimationFrame(() => {
                style.height = '0px';
            });
        }
    }
}

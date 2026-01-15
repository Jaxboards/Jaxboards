import register, { Component } from '../JAX/component';

export default class CollapseBox extends Component<HTMLDivElement> {
    private fullHeight = 0;

    private readonly animationLengthInMs = 200;

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

        // Toggle initial state
        this.toggle();

        this.element.addEventListener('click', (event) => {
            if (
                event.target instanceof HTMLElement &&
                event.target.matches('.collapse-button')
            ) {
                this.isOpen = !this.isOpen;
                this.toggle();
            }
        });
    }

    get boxID() {
        return this.element.getAttribute('id') || '';
    }

    get collapseContent() {
        return this.element.querySelector<HTMLDivElement>('.collapse-content');
    }

    get isOpen() {
        return !(globalThis.sessionStorage.getItem('collapsed') ?? '')
            .split(',')
            .includes(this.boxID);
    }

    set isOpen(isOpen: boolean) {
        let list = (globalThis.sessionStorage.getItem('collapsed') ?? '')
            ?.split(',')
            .filter(Boolean);

        if (isOpen) {
            list = list.filter((item) => item !== this.boxID);
        } else {
            list?.push(this.boxID);
        }

        globalThis.sessionStorage.setItem('collapsed', list.join(',') ?? '');
    }

    toggle() {
        const { collapseContent } = this;

        if (!collapseContent) return;

        const { style } = collapseContent;

        if (this.isOpen) {
            if (!this.fullHeight) return;

            style.height = `${this.fullHeight}px`;
            this.element.classList.remove('collapsed');
            setTimeout(() => {
                style.removeProperty('height');
                style.removeProperty('overflow');
            }, this.animationLengthInMs);
        } else {
            this.fullHeight =
                collapseContent.clientHeight || collapseContent.offsetHeight;
            style.height = `${this.fullHeight}px`;
            style.overflow = 'hidden';
            this.element.classList.add('collapsed');
            requestAnimationFrame(() => {
                style.height = '0px';
            });
        }
    }
}

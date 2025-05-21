export default class PageList {
    element: HTMLDivElement;

    static selector(container: HTMLElement): void {
        container
            .querySelectorAll<HTMLDivElement>('.pages')
            .forEach((el) => new this(el));
    }

    constructor(element: HTMLDivElement) {
        this.element = element;
        element.addEventListener('wheel', (event) => this.wheel(event));
    }

    wheel(event: WheelEvent) {
        event.preventDefault();
        const direction = Math.sign(event.deltaY);
        const pages = Array.from(this.element.querySelectorAll('a'));
        const startPage = parseInt(pages[1].innerHTML, 10);
        const lastPage = parseInt(pages[pages.length - 1].innerHTML, 10);
        const between = pages.length - 2;

        if (
            (direction > 0 && startPage + between < lastPage) ||
            (direction < 0 && startPage > 2)
        ) {
            for (let x = 0; x < between; x += 1) {
                pages[x + 1].href = pages[x + 1].href.replace(
                    /\d+$/,
                    `${x + startPage + direction}`,
                );
                pages[x + 1].innerHTML = `${startPage + x + direction}`;
            }
        }
    }
}

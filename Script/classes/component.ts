export default class Component {
    element: HTMLElement;

    static get selector(): string {
        throw new Error('No Selector defined');
    }

    constructor(element: HTMLElement) {
        this.element = element;
    }
}

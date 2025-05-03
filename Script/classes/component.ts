export default class Component {
    static get selector() {
        throw new Error('No Selector defined');
    }

    constructor(element) {
        this.element = element;
        element.hydrated = true;
    }
}

import { insertAfter } from '../JAX/el';

export default class Switch {
    element: HTMLInputElement;

    static selector(container: HTMLElement) {
        return container
            .querySelectorAll<HTMLInputElement>('input.switch')
            .forEach((el) => new this(el));
    }

    constructor(element: HTMLInputElement) {
        this.element = element;
        // Hide original checkbox
        element.style.display = 'none';

        const button = Object.assign(document.createElement('button'), {
            type: 'button',
            title: element.className,
            className: element.className,
        });

        const toggle = () => {
            button.style.backgroundPosition = element.checked
                ? 'top'
                : 'bottom';
        };
        toggle();
        button.addEventListener('click', () => {
            element.checked = !element.checked;
            toggle();
            element.dispatchEvent(new Event('change'));
        });
        insertAfter(button, element);
    }
}

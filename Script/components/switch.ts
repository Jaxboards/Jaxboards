import register, { Component } from '../JAX/component';
import { insertAfter } from '../JAX/el';

export default class Switch extends Component<HTMLInputElement> {
    static hydrate(container: HTMLElement): void {
        register(
            'Switch',
            container.querySelectorAll<HTMLInputElement>('input.switch'),
            this,
        );
    }

    constructor(element: HTMLInputElement) {
        super(element);

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

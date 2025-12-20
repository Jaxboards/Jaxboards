/* global RUN */

import register, { Component } from '../JAX/component';

export default class Form extends Component<HTMLFormElement> {
    private readonly resetOnSubmit: boolean;

    static hydrate(container: HTMLElement): void {
        register(
            'Form',
            container.querySelectorAll<HTMLFormElement>('form[data-ajax-form]'),
            this,
        );
    }

    constructor(element: HTMLFormElement) {
        super(element);
        this.resetOnSubmit = element.dataset.ajaxForm === 'resetOnSubmit';
        element.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitForm();
        });
    }

    submitForm() {
        const { element, resetOnSubmit } = this;
        const postBody = new URLSearchParams();
        const names = [];
        const values = [];
        const { submitButton } = element;

        const inputFields = ['input', 'select', 'button', 'textarea'] as const;

        inputFields
            .flatMap((tagName) => Array.from(element.querySelectorAll(tagName)))
            .forEach((inputField) => {
                if (!inputField.name || inputField.type === 'submit') {
                    return;
                }

                if (
                    inputField instanceof HTMLSelectElement &&
                    inputField.type === 'select-multiple'
                ) {
                    Array.from(inputField.options)
                        .filter((option) => option.selected)
                        .forEach((option) => {
                            names.push(`${inputField.name}[]`);
                            values.push(option.value);
                        });
                    return;
                }

                if (
                    inputField instanceof HTMLInputElement &&
                    ['checkbox', 'radio'].includes(inputField.type) &&
                    !inputField.checked
                ) {
                    return;
                }
                postBody.append(inputField.name, inputField.value);
            });

        if (submitButton) {
            postBody.append(submitButton.name, submitButton.value);
        }
        RUN.stream.load(element.action || globalThis.location.toString(), {
            body: postBody,
        });
        if (resetOnSubmit) {
            element.reset();
        }
        RUN.stream.pollData();
    }
}

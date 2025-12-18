/* global RUN */

export default class Form {
    private form: HTMLFormElement;

    private resetOnSubmit: boolean;

    static hydrate(container: HTMLElement): void {
        container
            .querySelectorAll<HTMLFormElement>('form[data-ajax-form]')
            .forEach((ajaxForm) => new this(ajaxForm));
    }

    constructor(form: HTMLFormElement) {
        this.form = form;
        this.resetOnSubmit = form.dataset.ajaxForm === 'resetOnSubmit';
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitForm();
        });
    }

    submitForm() {
        const { form, resetOnSubmit } = this;
        const names = [];
        const values = [];
        const { submitButton } = form;

        const inputFields = ['input', 'select', 'button', 'textarea'] as const;

        inputFields
            .flatMap((tagName) => Array.from(form.querySelectorAll(tagName)))
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
                names.push(inputField.name);
                values.push(inputField.value);
            });

        if (submitButton) {
            names.push(submitButton.name);
            values.push(submitButton.value);
        }
        RUN.stream.load(form.action || globalThis.location, {
            data: [names, values],
        });
        if (resetOnSubmit) {
            form.reset();
        }
        RUN.stream.pollData();
    }
}

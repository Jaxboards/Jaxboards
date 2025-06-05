export default class BetterSelect extends HTMLElement {
    shadow: ShadowRoot;
    internals: ElementInternals;

    results: HTMLDivElement;
    search: HTMLInputElement;
    leaveHandler: (event: FocusEvent) => void;
    optionKeyboardHandler: (event: KeyboardEvent) => void;
    hiddenFormField: HTMLInputElement;

    static formAssociated = true;

    static selector() {
        customElements.define('better-select', BetterSelect);
        customElements.define('better-option', BetterOption);
    }

    constructor() {
        super();

        this.shadow = this.attachShadow({ mode: 'open' });
        this.internals = this.attachInternals();
        this.search = document.createElement('input');


        this.results = document.createElement('div');
        // This form field becomes the output
        this.hiddenFormField = Object.assign(document.createElement('input'), {
            type: 'hidden',
            name: this.getAttribute('name'),
        });

        this.leaveHandler = (event) => {
            this.results.classList.toggle(
                'open',
                event.relatedTarget instanceof HTMLElement &&
                    this.results.contains(event.relatedTarget),
            );
        };

        this.optionKeyboardHandler = function (event) {
            switch (event.key) {
                case 'ArrowUp':
                    (this.previousElementSibling as HTMLButtonElement)?.focus();
                    break;
                case 'ArrowDown':
                    (this.nextElementSibling as HTMLButtonElement).focus();
                    break;
            }
        };
    }

    connectedCallback() {
        const { shadow, results, search } = this;
        results.className = 'results';

        search.autocomplete = 'off';
        search.onfocus = () => results.classList.toggle('open', true);
        search.oninput = () => {
            this.renderOptions(search.value);
        };
        search.onkeydown = (event) => {
            switch (event.key) {
                case 'ArrowUp':
                    this.shadow
                        .querySelector<HTMLButtonElement>(
                            '.results button:last-child',
                        )
                        ?.focus();
                    break;
                case 'ArrowDown':
                    this.shadow
                        .querySelector<HTMLButtonElement>(
                            '.results button:first-child',
                        )
                        ?.focus();
                    break;
            }
        };
        search.onblur = this.leaveHandler;

        shadow.appendChild(search);
        shadow.appendChild(this.hiddenFormField);
        shadow.appendChild(results);
        this.renderOptions();
        shadow.adoptedStyleSheets = [this.stylesheet()];

        // ARIA stuff for a11y
        search.setAttribute('role', 'combobox');
        search.setAttribute('aria-activedescendant', '');
        results.setAttribute('role', 'listbox');
        const controlsId = `id-${Math.random()}`;
        results.setAttribute('id', controlsId);
        search.setAttribute('aria-controls', controlsId);

        this.updateValue();
    }

    close() {
        this.results.classList.remove('open');
    }

    clear() {
        [
            ...Array.from(this.querySelectorAll('better-option[selected]')),
            ...Array.from(this.results.querySelectorAll('.selected')),
        ].forEach((option) => {
            option.removeAttribute('selected');
            option.classList.remove('selected');
        });
    }

    renderOptions(searchTerm = '') {
        this.results.innerHTML = '';
        this.getOptions(searchTerm).forEach((option) =>
            this.results.appendChild(option),
        );
    }

    getOptions(searchTerm = ''): Array<HTMLElement> {
        return Array.from(this.querySelectorAll<HTMLElement>('better-option'))
            .filter((betterOption) =>
                betterOption.innerText
                    .toLowerCase()
                    .includes(searchTerm.toLowerCase()),
            )
            .map((betterOption) => {
                const button = document.createElement('button');
                button.onclick = () => {
                    if (!this.hasAttribute('multiple')) {
                        this.clear();
                        this.close();
                    }
                    button.classList.toggle('selected');
                    betterOption.toggleAttribute(
                        'selected',
                        button.classList.contains('selected'),
                    );
                    this.updateValue();
                };
                button.onkeydown = this.optionKeyboardHandler;
                button.classList.toggle(
                    'selected',
                    betterOption.hasAttribute('selected'),
                );

                const imageSrc = betterOption.getAttribute('image');
                if (imageSrc) {
                    const image = new Image();
                    image.src = imageSrc;
                    button.appendChild(image);
                }

                const label = document.createTextNode(' ' + betterOption.innerText);
                button.appendChild(label);
                button.onblur = this.leaveHandler;

                return button;
            })
            .filter(Boolean);
    }

    updateValue() {
        const selectedOptions = Array.from(this.querySelectorAll<HTMLElement>('better-option[selected]'));
        const value = selectedOptions
                .map((option) => option.getAttribute('value'))
                .join(',');
        this.search.value = selectedOptions.at(-1)?.innerText ?? '';
        this.internals.setFormValue(value);
    }

    stylesheet() {
        const style = new CSSStyleSheet();

        style.replaceSync(`
            button { display: block; width: 100%; text-align: left; }
            button.selected { background: #1f1f83; color: #FFF; }
            button:hover { background: #9cdcfe; }
            button img { vertical-align: middle; }
            .results { position: absolute; display: none; max-height: 400px; overflow: auto; }
            .results.open { display: block; }
            .better-option { display: none; }
        `);

        return style;
    }
}

class BetterOption extends HTMLElement {
    constructor() {
        super();
    }
}

export default class BetterSelect extends HTMLElement {
    // This is accessed by the browser to mark this as a form element
    // do not remove
    static readonly formAssociated = true;

    shadow: ShadowRoot;

    internals: ElementInternals;

    results: HTMLDivElement;

    search: HTMLInputElement;

    leaveHandler: (event: FocusEvent) => void;

    optionKeyboardHandler: (event: KeyboardEvent) => void;

    hiddenFormField: HTMLInputElement;

    value: string;

    static hydrate() {
        customElements.define('better-select', BetterSelect);

        customElements.define(
            'better-option',
            class BetterOption extends HTMLElement {},
        );
    }

    constructor() {
        super();

        this.value = this.getAttribute('value') ?? '';
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

        this.optionKeyboardHandler = function optionKeyboardHandler(event) {
            switch (event.key) {
                case 'ArrowUp':
                    (this.previousElementSibling as HTMLButtonElement)?.focus();
                    break;
                case 'ArrowDown':
                    (this.nextElementSibling as HTMLButtonElement).focus();
                    break;
                default:
                    break;
            }
        };
    }

    connectedCallback() {
        const { shadow, results, search } = this;
        results.className = 'results';

        search.autocomplete = 'off';
        search.addEventListener('focus', () =>
            results.classList.toggle('open', true),
        );
        search.addEventListener('input', () => {
            this.renderOptions(search.value);
        });
        search.addEventListener('keydown', (event) => {
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
                default:
                    break;
            }
        });
        search.addEventListener('blur', this.leaveHandler);

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

    getOptions(searchTerm = ''): HTMLElement[] {
        return Array.from(this.querySelectorAll<HTMLElement>('better-option'))
            .filter((betterOption) =>
                betterOption.innerText
                    .toLowerCase()
                    .includes(searchTerm.toLowerCase()),
            )
            .map((betterOption) => {
                const button = document.createElement('button');
                button.addEventListener('click', () => {
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
                });
                button.addEventListener('keydown', this.optionKeyboardHandler);
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

                const label = document.createTextNode(
                    ` ${betterOption.innerText}`,
                );
                button.appendChild(label);
                button.addEventListener('blur', this.leaveHandler);

                return button;
            })
            .filter(Boolean);
    }

    updateValue() {
        const selectedOptions = Array.from(
            this.querySelectorAll<HTMLElement>('better-option[selected]'),
        );
        this.value = selectedOptions
            .map((option) => option.getAttribute('value'))
            .join(',');
        this.search.value = selectedOptions.at(-1)?.innerText ?? '';
        this.internals.setFormValue(this.value);
        this.dispatchEvent(new Event('change'));
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
        `);

        return style;
    }
}

import Ajax from '../JAX/ajax';
import register, { Component } from '../JAX/component';
import { getCoordinates, getHighestZIndex } from '../JAX/el';

const VALID_CLASS = 'valid';
const INVALID_CLASS = 'invalid';

export default class AutoComplete extends Component<HTMLInputElement> {
    action?: string;

    outputElement: HTMLInputElement;

    indicatorElement?: HTMLElement | null;

    static hydrate(container: HTMLElement): void {
        register(
            'AutoComplete',
            container.querySelectorAll<HTMLInputElement>(
                'input[data-autocomplete-action]',
            ),
            this,
        );
    }

    constructor(element: HTMLInputElement) {
        super(element);

        // Disable native autocomplete behavior
        element.autocomplete = 'off';

        this.action = element.dataset.autocompleteAction;
        const output = element.dataset.autocompleteOutput;
        const indicator = element.dataset.autocompleteIndicator;

        const outputElement = output
            ? document.querySelector<HTMLInputElement>(output)
            : undefined;

        if (!outputElement) {
            throw new Error(
                'Expected element to have data-autocomplete-output',
            );
        }

        this.outputElement = outputElement || document.createElement('input');
        this.indicatorElement = indicator
            ? document.querySelector(indicator)
            : undefined;

        element.addEventListener('keyup', (event) => this.keyUp(event));
        element.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    }

    getResultsContainer() {
        const coords = getCoordinates(this.element);
        let resultsContainer =
            document.querySelector<HTMLElement>('#autocomplete');
        if (!resultsContainer) {
            resultsContainer = Object.assign(document.createElement('div'), {
                id: 'autocomplete',
            });
            Object.assign(resultsContainer.style, {
                zIndex: getHighestZIndex(),
            });
            document.body.appendChild(resultsContainer);
        }

        // Position and size the dropdown below the input field
        Object.assign(resultsContainer.style, {
            top: `${coords.yh}px`,
            left: `${coords.x}px`,
            width: `${coords.w}px`,
        });

        return resultsContainer;
    }

    keyUp(event: KeyboardEvent) {
        const resultsContainer = this.getResultsContainer();
        const results = Array.from(resultsContainer.querySelectorAll('div'));
        const selectedIndex = results.findIndex((el) =>
            el.classList.contains('selected'),
        );

        // Handle arrow key selection
        if (results) {
            switch (event.key) {
                case 'ArrowUp':
                    if (selectedIndex >= 0) {
                        results[selectedIndex].classList.remove('selected');
                        results[selectedIndex - 1].classList.add('selected');
                    }
                    return;
                case 'ArrowDown':
                    if (selectedIndex === -1) {
                        results[0].classList.add('selected');
                    } else if (selectedIndex < results.length - 1) {
                        results[selectedIndex].classList.remove('selected');
                        results[selectedIndex + 1].classList.add('selected');
                    }
                    return;
                case 'Enter':
                    if (selectedIndex >= 0) {
                        results[selectedIndex].dispatchEvent(
                            new Event('click'),
                        );
                    }
                    return;
                default:
                    if (this.indicatorElement) {
                        this.indicatorElement.classList.remove(VALID_CLASS);
                        this.indicatorElement.classList.add(INVALID_CLASS);
                    }
                    break;
            }
        }

        const relativePath = document.location.toString().match('/ACP/')
            ? '../'
            : '';
        const searchTerm = encodeURIComponent(this.element.value);
        const queryParams = `act=${this.action}&term=${searchTerm}`;
        new Ajax().load(`${relativePath}api/?${queryParams}`, {
            callback: (xml: XMLHttpRequest) => {
                const data = JSON.parse(xml.responseText);
                resultsContainer.innerHTML = '';
                if (!data.length) {
                    resultsContainer.style.display = 'none';
                } else {
                    resultsContainer.style.display = '';
                    const [ids, values] = data;
                    ids.forEach((key: number, i: number) => {
                        const value = values[i];
                        const div = document.createElement('div');
                        div.innerHTML = value;
                        div.onclick = () => {
                            resultsContainer.style.display = 'none';
                            if (this.indicatorElement) {
                                this.indicatorElement.classList.add(
                                    VALID_CLASS,
                                );
                            }
                            this.outputElement.value = `${key}`;
                            this.outputElement.dispatchEvent(
                                new Event('change'),
                            );
                            this.element.value = value;
                        };
                        resultsContainer.appendChild(div);
                    });
                }
            },
        });
    }
}

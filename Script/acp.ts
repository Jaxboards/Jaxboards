import { getCoordinates } from './JAX/el';
import { replaceSelection } from './JAX/selection';
import sortableTree from './JAX/sortable-tree';
import { onDOMReady } from './JAX/util';
import AutoComplete from './components/auto-complete';
import BetterSelect from './components/better-select';
import type { HTMLFormWithSubmit } from './components/form';
import Switch from './components/switch';

function dropdownMenu(e: MouseEvent) {
    const el = e.target;

    if (!(el instanceof HTMLElement)) {
        return;
    }

    if (el?.tagName.toLowerCase() === 'a') {
        const menu = document.querySelector<HTMLDivElement>(
            `#menu_${el.classList[0]}`,
        );
        if (!menu) {
            return;
        }

        el.classList.add('active');
        const s = menu.style;
        s.display = 'block';
        const p = getCoordinates(el);
        s.top = `${p.y + el.clientHeight}px`;
        s.left = `${p.x}px`;
        el.addEventListener('mouseout', (e2: MouseEvent) => {
            if (
                e2.relatedTarget instanceof HTMLElement &&
                e2.relatedTarget !== menu &&
                e2.relatedTarget.offsetParent !== menu
            ) {
                el.classList.remove('active');
                menu.style.display = 'none';
            }
        });
        menu?.addEventListener('mouseout', (e2: MouseEvent) => {
            if (
                e2.relatedTarget instanceof HTMLElement &&
                e2.relatedTarget !== el &&
                e2.relatedTarget.offsetParent !== menu &&
                e2.relatedTarget !== menu
            ) {
                el.classList.remove('active');
                menu.style.display = 'none';
            }
        });
    }
}

async function submitForm(form: HTMLFormWithSubmit) {
    const formData = new FormData(form, form.submitButton);

    // Filter out input[type=file]
    const withoutFiles = Array.from(formData.entries()).filter(
        (tuple): tuple is [string, string] => typeof tuple[1] === 'string',
    );

    await fetch(document.location.search, {
        method: 'POST',
        body: new URLSearchParams(withoutFiles),
        headers: {
            'X-JSACCESS': `1`,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
    });

    alert("Saved. Ajax-submitted so you don't lose your place");
}

function gracefulDegrade() {
    // Dropdown menu
    document
        .querySelector<HTMLDivElement>('#nav')
        ?.addEventListener('mouseover', dropdownMenu);

    Array.from(document.querySelectorAll<HTMLFormWithSubmit>('form')).forEach(
        (form) => {
            if (!form.dataset.useAjaxSubmit) {
                return;
            }
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                void submitForm(form);
            });
        },
    );

    // Initialize components
    AutoComplete.hydrate(document.body);
    Switch.hydrate(document.body);
    BetterSelect.hydrate();

    // Makes editors capable of tabbing for indenting
    const editor = document.querySelector<HTMLTextAreaElement>('.editor');
    if (editor) {
        editor.addEventListener('keydown', (event: KeyboardEvent) => {
            if (event.key === 'Tab') {
                replaceSelection(editor, '    ');
                event.preventDefault();
            }
        });
    }

    // Hook up autocomplete form fields

    // Orderable forums needs this
    const tree = document.querySelector<HTMLUListElement>('.tree');
    const ordered = document.querySelector<HTMLInputElement>('#ordered');
    if (tree && ordered) {
        sortableTree(tree, 'forum_', ordered);
    }
}
onDOMReady(gracefulDegrade);

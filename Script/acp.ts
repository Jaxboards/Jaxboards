import { onDOMReady } from './JAX/util';
import { getCoordinates } from './JAX/el';
import Ajax from './JAX/ajax';
import { replaceSelection } from './JAX/selection';
import sortableTree from './JAX/sortable-tree';
import AutoComplete from './components/auto-complete';
import Switch from './components/switch';

function dropdownMenu(e) {
    const el = e.target;

    if (el?.tagName.toLowerCase() === 'a') {
        const menu = document.querySelector(`#menu_${el.classList[0]}`);
        el.classList.add('active');
        const s = menu.style;
        s.display = 'block';
        const p = getCoordinates(el);
        s.top = `${p.y + el.clientHeight}px`;
        s.left = `${p.x}px`;
        el.onmouseout = (e2) => {
            if (!e2.relatedTarget && e2.toElement)
                e2.relatedTarget = e2.toElement;
            if (
                e2.relatedTarget !== menu &&
                e2.relatedTarget.offsetParent !== menu
            ) {
                el.classList.remove('active');
                menu.style.display = 'none';
            }
        };
        menu.onmouseout = (e2) => {
            if (!e2.relatedTarget && e2.toElement)
                e2.relatedTarget = e2.toElement;
            if (
                e2.relatedTarget !== el &&
                e2.relatedTarget.offsetParent !== menu &&
                e2.relatedTarget !== menu
            ) {
                el.classList.remove('active');
                menu.style.display = 'none';
            }
        };
    }
}

function submitForm(form) {
    const names = [];
    const values = [];
    const elements = Array.from(form.elements);
    const submit = form.submitButton;
    elements.forEach((element) => {
        if (!element.name || element.type === 'submit') return;
        if (
            (element.type === 'checkbox' || element.type === 'radio') &&
            !element.checked
        ) {
            return;
        }
        names.push(element.name);
        values.push(element.value);
    });

    if (submit) {
        names.push(submit.name);
        values.push(submit.value);
    }
    new Ajax().load(document.location.search, { data: [names, values] });
    // eslint-disable-next-line no-alert
    alert("Saved. Ajax-submitted so you don't lose your place");
}

function gracefulDegrade() {
    // Dropdown menu
    document.querySelector('#nav').addEventListener('mouseover', dropdownMenu);

    Array.from(document.querySelectorAll('form[data-use-ajax-submit]')).forEach(
        (form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                submitForm(form);
            });
        },
    );

    // Converts all switches (checkboxes) into graphics, to show "X" or "check"
    document
        .querySelectorAll(Switch.selector)
        .forEach((toggleSwitch) => new Switch(toggleSwitch));

    // Makes editors capable of tabbing for indenting
    const editor = document.querySelector('.editor');
    if (editor) {
        editor.addEventListener('keydown', (event) => {
            if (event.keyCode === 9) {
                replaceSelection(editor, '    ');
                event.preventDefault();
            }
        });
    }

    // Hook up autocomplete form fields
    const autoCompleteFields = document.querySelectorAll(AutoComplete.selector);
    autoCompleteFields.forEach((field) => new AutoComplete(field));

    // Orderable forums needs this
    const tree = document.querySelector('.tree');
    if (tree) {
        sortableTree(tree, 'forum_', document.querySelector('#ordered'));
    }
}
onDOMReady(gracefulDegrade);

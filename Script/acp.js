import { onDOMReady } from './JAX/util';
import { insertAfter, getCoordinates } from './JAX/el';
import Ajax from './JAX/ajax';
import Editor from './JAX/editor';
import sortableTree from './JAX/sortable-tree';
import autoComplete from './JAX/autocomplete';

function dropdownMenu(e) {
  const el = e.target;
  if (el.tagName.toLowerCase() === 'a') {
    const menu = document.querySelector(`#menu_${el.classList[0]}`);
    el.classList.add('active');
    const s = menu.style;
    s.display = 'block';
    const p = getCoordinates(el);
    s.top = `${p.y + el.clientHeight}px`;
    s.left = `${p.x}px`;
    el.onmouseout = (e2) => {
      if (!e2.relatedTarget && e2.toElement) e2.relatedTarget = e2.toElement;
      if (e2.relatedTarget !== menu && e2.relatedTarget.offsetParent !== menu) {
        el.classList.remove('active');
        menu.style.display = 'none';
      }
    };
    menu.onmouseout = (e2) => {
      if (!e2.relatedTarget && e2.toElement) e2.relatedTarget = e2.toElement;
      if (
        e2.relatedTarget !== el
        && e2.relatedTarget.offsetParent !== menu
        && e2.relatedTarget !== menu
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
      (element.type === 'checkbox' || element.type === 'radio')
      && !element.checked
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

// TODO: Remove all globals in this file
window.ACP = {
  getCoordinates,
};

function makestuffcool() {
  // Dropdown menu
  document.querySelector('#nav').addEventListener('mouseover', dropdownMenu);

  Array.from(document.querySelectorAll('form[data-use-ajax-submit]')).forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      submitForm(form);
    });
  });

  // Converts all switches (checkboxes) into graphics, to show "X" or "check"
  const switches = Array.from(document.querySelectorAll('.switch'));
  switches.forEach((switchEl) => {
    const toggle = document.createElement('div');
    toggle.className = switchEl.className.replace('switch', 'switch_converted');
    switchEl.style.display = 'none';
    if (!switchEl.checked) {
      toggle.style.backgroundPosition = 'bottom';
    }
    toggle.addEventListener('click', () => {
      switchEl.checked = !switchEl.checked;
      toggle.style.backgroundPosition = switchEl.checked ? 'top' : 'bottom';
      switchEl.dispatchEvent(new Event('change'));
    });
    insertAfter(toggle, switchEl);
  });

  // Makes editors capable of tabbing for indenting
  const editor = document.querySelector('.editor');
  if (editor) {
    editor.addEventListener('keydown', (event) => {
      if (event.keyCode === 9) {
        Editor.setSelection(editor, '    ');
        event.preventDefault();
      }
    });
  }

  // Hook up autocomplete form fields
  const autoCompleteFields = document.querySelectorAll('[data-autocomplete-action]');
  autoCompleteFields.forEach((field) => {
    // Disable native autocomplete behavior
    field.autocomplete = 'off';
    const action = field.dataset.autocompleteAction;
    const output = field.dataset.autocompleteOutput;
    const indicator = field.dataset.autocompleteIndicator;
    const outputElement = output && document.querySelector(output);
    const indicatorElement = indicator && document.querySelector(indicator);
    const searchTerm = field.value;

    if (outputElement) {
      outputElement.addEventListener('change', () => {
        indicatorElement.classList.add('good');
      });
    }
    field.addEventListener('keyup', (event) => {
      indicatorElement.classList.remove('good');
      indicatorElement.classList.add('bad');
      autoComplete(`act=${action}&term=${encodeURIComponent(searchTerm)}`, field, outputElement, event);
    });
  });

  // Orderable forums needs this
  const tree = document.querySelector('.tree');
  if (tree) {
    sortableTree(tree, 'forum_', document.querySelector('#ordered'));
  }
}
onDOMReady(makestuffcool);

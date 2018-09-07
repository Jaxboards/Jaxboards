import {
  onDOMReady,
} from './JAX/util';
import {
  insertAfter,
  getCoordinates,
} from './JAX/el';
import Ajax from './JAX/ajax';
import Editor from './JAX/editor';

// TODO: make this not global
window.dropdownMenu = function dropdownMenu(e) {
  const el = e.srcElement || e.target;
  if (el.tagName.toLowerCase() === 'a') {
    const menu = document.querySelector(`#menu_${el.className}`);
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
};

function makestuffcool() {
  const switches = Array.from(document.querySelectorAll('.switch'));
  switches.forEach((switchEl) => {
    const t = document.createElement('div');
    t.className = switchEl.className.replace('switchEl', 'switchEl_converted');
    t.s = switchEl;
    switchEl.t = t;
    switchEl.style.display = 'none';
    if (!switchEl.checked) t.style.backgroundPosition = 'bottom';
    const set = (onoff = switchEl.checked) => {
      switchEl.checked = onoff;
      switchEl.t.style.backgroundPosition = switchEl.checked ? 'top' : 'bottom';
      if (switchEl.onchange) switchEl.onchange();
    };
    t.onclick = () => set();
    insertAfter(t, switchEl);
  });

  const editor = document.querySelector('.editor');
  if (editor) {
    editor.onkeydown = (event) => {
      if (event.keyCode === 9) {
        Editor.setSelection(editor, '    ');
        return false;
      }
      return true;
    };
  }
}
onDOMReady(makestuffcool);

window.submitForm = function submitForm(a) {
  const names = [];
  const values = [];
  let x;
  const elements = Array.from(a.elements);
  const submit = a.submitButton;
  elements.forEach((element) => {
    if (!element.name || element.type === 'submit') return;
    if ((element.type === 'checkbox' || a[x].type === 'radio') && !a[x].checked) {
      return;
    }
    names.push(a[x].name);
    values.push(a[x].value);
  });

  if (submit) {
    names.push(submit.name);
    values.push(submit.value);
  }
  new Ajax().load(document.location.search, 0, [names, values], 1, 1);
  alert("Saved. Ajax-submitted so you don't lose your place");
  return false;
};

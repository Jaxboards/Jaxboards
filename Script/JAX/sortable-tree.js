import Drag from './drag';
import { insertBefore, insertAfter, isChildOf } from './el';

function parsetree(tree, prefix) {
  const nodes = Array.from(tree.querySelectorAll('li'));
  const order = {};
  let gotsomethin = 0;
  nodes.forEach(node => {
    if (node.className !== 'seperator' && node.parentNode === tree) {
      gotsomethin = 1;
      const [sub] = node.getElementsByTagName('ul');
      order[`_${node.id.substr(prefix.length)}`] =
        sub !== undefined ? parsetree(sub, prefix) : 1;
    }
  });
  return gotsomethin ? order : 1;
}

export default function(tree, prefix, formfield) {
  const listItems = Array.from(tree.querySelectorAll('li'));
  const items = [];
  const seperators = [];

  items.push(...listItems.filter(li => li.className !== 'title'));

  items.forEach(item => {
    const tmp = document.createElement('li');
    tmp.className = 'seperator';
    seperators.push(tmp);
    insertBefore(tmp, item);
  });

  const drag = new Drag().noChildActivation();
  drag.drops(seperators.concat(items)).addListener({
    ondragover(a) {
      a.droptarget.style.border = '1px solid #000';
    },
    ondragout(a) {
      a.droptarget.style.border = 'none';
    },
    ondrop(a) {
      const next = a.droptarget.nextSibling;
      let tmp;
      const parentlock = a.el.className === 'parentlock';
      const nofirstlevel = a.el.className === 'nofirstlevel';
      if (a.droptarget) {
        a.droptarget.style.border = 'none';
      }
      if (a.droptarget.className === 'seperator') {
        if (parentlock && a.droptarget.parentNode !== a.el.parentNode) {
          return drag.reset(a.el);
        }
        if (nofirstlevel && a.droptarget.parentNode.className === 'tree') {
          return drag.reset(a.el);
        }
        if (isChildOf(a.droptarget, a.el) || a.el === next) {
          return drag.reset(a.el);
        }
        if (next.className === 'spacer') {
          next.parentNode.removeChild(next);
        }
        if (next.className !== 'spacer') {
          insertAfter(a.el.previousSibling, a.droptarget);
        } else {
          a.el.previousSibling.parentNode.removeChild(a.el.previousSibling);
        }
        insertAfter(a.el, a.droptarget);
      } else if (!parentlock && a.droptarget.tagName === 'LI') {
        [tmp] = a.droptarget.getElementsByTagName('ul');
        if (!tmp) {
          tmp = document.createElement('ul');
          a.droptarget.appendChild(tmp);
        }
        tmp.appendChild(a.el.previousSibling);
        tmp.appendChild(a.el);
        a.droptarget.appendChild(tmp);
      }
      drag.reset(a.el);
      if (formfield) {
        formfield.value = JSON.stringify(parsetree(tree, prefix));
      }
      return null;
    }
  });

  items.forEach(item => drag.apply(item));
}

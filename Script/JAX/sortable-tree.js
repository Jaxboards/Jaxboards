import Drag from './drag';
import {
  insertBefore,
  insertAfter,
  isChildOf,
} from './el';

export default function (tree, prefix, formfield) {
  let tmp = tree.querySelectorAll('li');
  let x;
  const items = [];
  const seperators = [];
  let all = [];
  let drag;
  for (x = 0; x < tmp.length; x++) {
    if (tmp[x].className != 'title') items.push(tmp[x]);
  }

  function parsetree(tree) {
    const nodes = tree.getElementsByTagName('li');
    const order = {};
    let node;
    let sub;
    let gotsomethin = 0;
    for (let x = 0; x < nodes.length; x++) {
      node = nodes[x];
      if (node.className != 'seperator' && node.parentNode == tree) {
        gotsomethin = 1;
        sub = node.getElementsByTagName('ul')[0];
        order[`_${node.id.substr(prefix.length)}`] = sub != undefined ? parsetree(sub) : 1;
      }
    }
    return gotsomethin ? order : 1;
  }

  for (x = 0; x < items.length; x++) {
    tmp = document.createElement('li');
    tmp.className = 'seperator';
    seperators.push(tmp);
    insertBefore(tmp, items[x]);
  }

  drag = new Drag().noChildActivation();
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
      let tmp2;
      const parentlock = a.el.className == 'parentlock';
      const nofirstlevel = a.el.className == 'nofirstlevel';
      if (a.droptarget) a.droptarget.style.border = 'none';
      if (a.droptarget.className == 'seperator') {
        if (parentlock && a.droptarget.parentNode != a.el.parentNode) {
          return drag.reset(a.el, 1);
        }
        if (nofirstlevel && a.droptarget.parentNode.className == 'tree') {
          return drag.reset(a.el, 1);
        }
        if (isChildOf(a.droptarget, a.el) || a.el == next) {
          return drag.reset(a.el, 1);
        }
        if (next.className == 'spacer') {
          next.parentNode.removeChild(next);
        }
        if (next.className != 'spacer') {
          insertAfter(a.el.previousSibling, a.droptarget);
        } else {
          a.el.previousSibling.parentNode.removeChild(a.el.previousSibling);
        }
        insertAfter(a.el, a.droptarget);
      } else if (!parentlock && a.droptarget.tagName == 'LI') {
        tmp = a.droptarget.getElementsByTagName('ul')[0];
        if (!tmp) {
          tmp = document.createElement('ul');
          a.droptarget.appendChild(tmp);
        }
        tmp.appendChild(a.el.previousSibling);
        tmp.appendChild(a.el);
        a.droptarget.appendChild(tmp);
      } else {
      }
      drag.reset(a.el, 1);
      if (formfield) {
        formfield.value = JSON.stringify(parsetree(tree));
      }
    },
  });

  all = items.concat(seperators);

  for (x = 0; x < items.length; x++) {
    drag.apply(items[x]);
  }
}

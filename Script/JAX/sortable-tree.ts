import Drag, { DragSession } from './drag';
import { insertAfter, insertBefore, isChildOf } from './el';

function parsetree(tree: HTMLElement, prefix: string) {
    const nodes = Array.from(tree.querySelectorAll('li'));
    const order: Record<string, unknown> = {};
    let gotsomethin = 0;
    nodes.forEach((node) => {
        if (node.className !== 'seperator' && node.parentNode === tree) {
            gotsomethin = 1;
            const [sub] = node.getElementsByTagName('ul');
            order[`_${node.id.slice(prefix.length)}`] = sub
                ? parsetree(sub, prefix)
                : 1;
        }
    });
    return gotsomethin ? order : 1;
}

function dropOnSeparator(sess: DragSession, drag: Drag) {
    if (!sess.droptarget) {
        return;
    }

    const parentlock = sess.el.classList.contains('parentlock');
    const nofirstlevel = sess.el.classList.contains('nofirstlevel');

    if (parentlock && sess.droptarget.parentNode !== sess.el.parentNode) {
        drag.reset(sess.el);
        return;
    }
    if (
        nofirstlevel &&
        (sess.droptarget.parentNode as HTMLElement)?.className === 'tree'
    ) {
        drag.reset(sess.el);
        return;
    }
    if (
        isChildOf(sess.droptarget, sess.el) ||
        sess.el === sess.droptarget.nextSibling
    ) {
        drag.reset(sess.el);
        return;
    }
    const next = sess.droptarget.nextSibling as HTMLElement;
    if (next.className === 'spacer') {
        next.remove();
    }
    if (next.className === 'spacer') {
        sess.el.previousSibling?.remove();
    } else {
        insertAfter(sess.el.previousSibling as HTMLLIElement, sess.droptarget);
    }
    insertAfter(sess.el, sess.droptarget);
}

export default function sortableTree(
    tree: HTMLElement,
    prefix: string,
    formfield: HTMLInputElement,
) {
    const listItems = Array.from(tree.querySelectorAll('li'));
    const items = [];
    const seperators: HTMLLIElement[] = [];

    items.push(...listItems.filter((li) => li.className !== 'title'));

    items.forEach((item) => {
        const tmp = document.createElement('li');
        tmp.className = 'seperator';
        seperators.push(tmp);
        insertBefore(tmp, item);
    });

    const drag = new Drag().noChildActivation();
    drag.drops([...items, ...seperators]).addListener({
        ondragover(sess: DragSession) {
            if (sess.droptarget)
                sess.droptarget.style.border = '1px solid #000';
        },
        ondragout(sess: DragSession) {
            if (sess.droptarget) sess.droptarget.style.border = 'none';
        },
        ondrop(sess: DragSession) {
            let tmp;
            const parentlock = sess.el.classList.contains('parentlock');
            drag.reset(sess.el);
            if (!sess.droptarget) {
                return;
            }
            sess.droptarget.style.border = 'none';
            if (sess.droptarget.className === 'seperator') {
                dropOnSeparator(sess, drag);
            } else if (!parentlock && sess.droptarget.tagName === 'LI') {
                [tmp] = sess.droptarget.getElementsByTagName('ul');
                if (!tmp) {
                    tmp = document.createElement('ul');
                    sess.droptarget.appendChild(tmp);
                }
                tmp.appendChild(sess.el.previousSibling!);
                tmp.appendChild(sess.el);
                sess.droptarget.appendChild(tmp);
            }
            if (formfield) {
                formfield.value = JSON.stringify(parsetree(tree, prefix));
            }
        },
    });

    items.forEach((item) => drag.apply(item));
}

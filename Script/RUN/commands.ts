/* global RUN, globalsettings */
/* eslint-disable no-alert */
import { toggleOverlay, onImagesLoaded } from '../JAX/util';
import Animation from '../JAX/animation';
import { addIdleClock } from '../JAX/date';
import { getCoordinates, getComputedStyle } from '../JAX/el';
import gracefulDegrade from '../JAX/graceful-degrade';
import openTooltip from '../JAX/tooltip';
import Window from '../JAX/window';
import Sound from '../sound';
import { messageReceived } from '../JAX/instant-messaging-window';

/**
 * These are all of the possible commands
 * that the server can send to the client.
 */
export default {
    loadscript([src]: [string]) {
        document.body.appendChild(
            Object.assign(document.createElement('script'), { src }),
        );
    },
    script([script]: [string]) {
        // eslint-disable-next-line
        eval(script);
    },
    error([message]: [string]) {
        alert(message);
    },
    alert([message]: [string]) {
        alert([message]);
    },
    reload() {
        window.location.reload();
    },
    refreshdata() {
        RUN.stream.pollData(true);
    },
    addclass([selector, className]: [string, string]) {
        const el = document.querySelector(selector);
        if (el) {
            el.classList.add(className);
        }
    },
    title([title]: [string]) {
        document.title = title;
    },
    update([sel, html, shouldHighlight]: [string, string, string]) {
        let selector = sel;
        const paths = Array.from(
            document.querySelectorAll<HTMLElement>('.path'),
        );
        if (selector === 'path' && paths.length > 1) {
            paths.forEach((path) => {
                path.innerHTML = html;
                gracefulDegrade(path);
            });
            return;
        }
        if (!/^\W/.test(selector)) {
            selector = `#${selector}`;
        }
        const el = document.querySelector<HTMLElement>(selector);
        if (!el) return;
        el.innerHTML = html;
        if (shouldHighlight) {
            new Animation(el).dehighlight().play();
        }
        gracefulDegrade(el);
    },
    removeel([selector]: [string]) {
        const el = document.querySelector(selector);
        if (el) el.parentNode?.removeChild(el);
    },
    overlay: toggleOverlay,
    back() {
        window.history.back();
    },
    setstatus([className]: [string]) {
        const status = document.querySelector('#status');
        if (status) {
            status.className = className;
        }
    },
    appendrows([selector, rowHTML]: [string, string]) {
        const table = document.querySelector<HTMLTableElement>(selector);
        if (!table) return;
        const span = document.createElement('span');
        span.innerHTML = `<table>${rowHTML}</table>`;
        const vtbody = span.getElementsByTagName('tbody')[0];
        // table=table.getElementsByTagName('tbody')[0],
        gracefulDegrade(vtbody);
        table.appendChild(vtbody);
    },
    location([path]: [string]) {
        if (path.charAt(0) === '?') RUN.stream.location(path);
        else {
            document.location = path;
        }
    },
    enable([selector]: [string]) {
        const el = document.querySelector<HTMLButtonElement>(`#${selector}`);
        if (el) {
            el.disabled = false;
        }
    },
    addshout([message]: [string]) {
        const ss = Array.from(
            document.querySelectorAll<HTMLDivElement>('#shoutbox .shout'),
        );
        let x;
        const span = document.createElement('span');
        span.innerHTML = message;
        const div = span.firstChild;
        ss[0].parentNode?.insertBefore(div, ss[0]);
        while (ss.length > globalsettings.shoutlimit - 1) {
            x = ss.pop();
            x.parentNode.removeChild(x);
        }
        new Animation(div).dehighlight().play();
        if (globalsettings.sound_shout) Sound.play('sbblip');
        gracefulDegrade(div);
    },
    tick([html]: [string]) {
        const ticker = document.querySelector('#ticker');
        if (!ticker) return;
        let tick = document.createElement('div');
        tick.className = 'tick';
        tick.innerHTML = html;
        tick.style.display = 'none';
        tick.style.overflow = 'hidden';
        ticker.insertBefore(tick, ticker.firstChild);
        const tickStyle = getComputedStyle(tick);
        tick.style.height = '0px';
        new Animation(tick).add('height', '0px', tickStyle?.height).play();
        const ticks = Array.from(
            ticker.querySelectorAll<HTMLDivElement>('.tick'),
        );
        const l = ticks.length;
        tick.style.display = 'block';

        if (l > 100) {
            for (let x = 100; x < l; x += 100) {
                tick = ticks[x];
                if (!tick.dataset.removed) {
                    tick = ticks[x];
                    new Animation(tick, 30, 500)
                        .add('opacity', '1', '0')
                        .then((el: HTMLDivElement) => {
                            el.parentNode?.removeChild(el);
                        })
                        .play();
                    tick.dataset.bonked = 'true';
                }
            }
        }
    },
    im([fromId, fromName, message, fromMe, timestamp]) {
        messageReceived({ fromId, fromName, message, fromMe, timestamp });
    },
    imtoggleoffline(a) {
        document.querySelector(`#im_${a}`).classList.add('offline');
    },
    window([options]) {
        const existingWindow =
            options.id && document.getElementById(options.id);
        if (existingWindow) {
            existingWindow.querySelector('.title').innerHTML = options.title;
            existingWindow.querySelector('.content').innerHTML =
                options.content;
            return;
        }
        const win = new Window();
        win.title = options.title;
        win.content = options.content;
        win.minimizable = options.minimizable || 0;
        win.useoverlay = options.useoverlay || 0;
        win.animate = options.animate !== undefined ? options.animate : true;
        win.resize = options.resize || false;
        win.className = options.className || '';
        if (options.onclose) {
            // eslint-disable-next-line no-eval
            win.onclose = eval(options.onclose);
        }
        if (options.pos) win.pos = options.pos;

        const winElement = win.create();
        winElement.id = options.id || '';
        gracefulDegrade(winElement);
    },
    closewindow([windowSelector]) {
        const el = document.querySelector(windowSelector);
        if (el) {
            Window.close(el);
        }
    },
    onlinelist(a) {
        const statusers = document.querySelector('#statusers');
        if (!statusers) {
            return;
        }
        a[0].forEach(
            ([memberId, groupId, status, name, tooltip, lastAction]) => {
                let link = document.querySelector(
                    `#statusers .user${memberId}`,
                );
                if (!link) {
                    link = document.createElement('a');
                    if (!Number.isNaN(parseInt(memberId, 10))) {
                        link.href = `?act=vu${memberId}`;
                    }
                    link.onclick = () => {
                        RUN.location(link.getAttribute('href'));
                    };
                }
                link.innerHTML = name;
                link.className = `user${memberId} mgroup${groupId} ${
                    status ? ` ${status}` : ''
                } lastAction${lastAction}`;
                if (tooltip) {
                    link.title = tooltip;
                    link.onmouseover = () => openTooltip(link, tooltip);
                }
                if (status === 'idle') {
                    addIdleClock(link);

                    return;
                }
                if (statusers.firstChild) {
                    statusers.insertBefore(link, statusers.firstChild);
                } else statusers.appendChild(link);
            },
        );
    },
    setoffline(a) {
        const statusers = document.querySelector('#statusers');
        const ids = a[0].split(',');
        ids.forEach((id) => {
            const link = document.querySelector(`#statusers .user${id}`);
            if (link) {
                statusers.removeChild(link);
            }
        });
    },

    scrollToPost([postId]) {
        const el = document.getElementById(`pid_${postId}`);
        if (!el) {
            return false;
        }
        onImagesLoaded(document.querySelectorAll('#page img')).then(() => {
            const pos = getCoordinates(el);
            window.scrollTo({ top: pos.y });
        });
        return true;
    },
    updateqreply(a) {
        const qreply = document.querySelector('#qreply');
        const textarea = qreply?.querySelector('textarea');
        if (textarea) {
            textarea.focus();
            textarea.value += a[0];
        }
    },
    newmessage([message, fromMID]) {
        let notification = document.querySelector('#notification');
        const num = document.querySelector('#num-messages');
        if (num) num.innerHTML = parseInt(num.innerHTML, 10) + 1;
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'notification';
            document.body.appendChild(notification);
        }
        notification.style.display = '';
        notification.className = 'newmessage';
        notification.onclick = () => {
            notification.style.display = 'none';
            RUN.stream.location(`?act=ucp&what=inbox&view=${fromMID}`, 3);
        };
        notification.innerHTML = message;
    },

    playsound(a) {
        Sound.loadAndPlay(a[0], a[1], !!a[2]);
    },
    attachfiles() {
        const el = document.querySelector('#attachfiles');
        el?.addEventListener('click', () => {
            alert('Attaching files is under construction');
        });
    },
    listrating([postId, html]: [string, string]) {
        let prdiv = document.querySelector<HTMLDivElement>(
            `#postrating_${postId}`,
        );
        let c;
        if (prdiv) {
            if (prdiv.style.display !== 'none') {
                new Animation(prdiv)
                    .add('height', '200px', '0px')
                    .then(() => {
                        prdiv.style.display = 'none';
                    })
                    .play();
                return;
            }
            prdiv.style.display = 'block';
        } else {
            prdiv = document.createElement('div');
            prdiv.className = 'postrating_list';
            prdiv.id = `postrating_${postId}`;
            c = getCoordinates(
                document.querySelector(`#pid_${postId} .postrating`),
            );
            prdiv.style.top = `${c.yh}px`;
            prdiv.style.left = `${c.x}px`;
            document.querySelector<HTMLDivElement>('#page')?.appendChild(prdiv);
        }
        prdiv.innerHTML = html;
        new Animation(prdiv).add('height', '0px', '200px').play();
    },
};

/* global RUN, globalsettings */

import Sound from '../sound';
import { flashTitle } from './flashing-title';
import gracefulDegrade from './graceful-degrade';
import Window from './window';

function notification(fromName, message) {
    flashTitle(`New message from ${fromName}!`);

    if (
        !document.hasFocus() &&
        window.Notification &&
        Notification.permission === 'granted'
    ) {
        const notify = new Notification(`${fromName} says:`, {
            body: message,
        });
        notify.onclick = () => {
            window.focus();
            notify.close();
        };
    }
}

function createMessagingWindow({ fromId, fromName, message }) {
    let messagesContainer = document.querySelector(`#im_${fromId} .ims`);

    if (messagesContainer) {
        return messagesContainer;
    }

    const imWindow = new Window({
        title: `${fromName}`,
        content: `
            <div class='ims'></div>
            <div class='offline'>This user may be offline</div>
            <div>
                <form data-ajax-form='resetOnSubmit' method='post'>
                    <input type='hidden' name='im_uid' value='%s' />
                    <input type='text' name='im_im' autocomplete='off' />
                    <input type='hidden' name='act' value='blank' />
                </form>
            </div>
        `.replace(/%s/g, fromId),
        className: 'im',
        resize: '.ims',
        animate: true,
    });

    const win = imWindow.create();
    gracefulDegrade(win);
    win.id = `im_${fromId}`;
    win.onclick = () => {
        win.querySelector('form').im_im.focus();
    };
    win.onclick();
    messagesContainer = document.querySelector(`#im_${fromId} .ims`);
    const test = getComputedStyle(messagesContainer);
    messagesContainer.style.width = test.width;
    messagesContainer.style.height = test.height;
    if (message && globalsettings.sound_im) Sound.play('imnewwindow');

    return messagesContainer;
}

export default function IMWindow(uid, uname) {
    if (!globalsettings.can_im) {
        // eslint-disable-next-line no-alert
        alert('You do not have permission to use this feature.');
        return;
    }

    RUN.stream.commands.im([uid, uname, false]);
}

export function messageReceived({
    fromId,
    fromName,
    message,
    fromMe,
    timestamp,
}) {
    notification(fromName, message);

    const messagesContainer = createMessagingWindow({
        fromId,
        fromName,
        message,
    });

    if (!message) {
        return;
    }

    const div = document.createElement('div');
    const isAction = message.substring(0, 3) === '/me';
    if (isAction) {
        div.className = 'action';
        /* eslint-disable no-param-reassign */
        message = message.substring(3);
        fromName = `***${fromName}`;
        /* eslint-enable no-param-reassign */
    }
    div.classList.add(fromMe ? 'you' : 'them');
    if (!fromMe) {
        document.querySelector(`#im_${fromId}`).classList.remove('offline');
    }
    div.innerHTML = `<a href='?act=vu${
        fromMe || parseInt(fromId, 10)
    }' class='name'>${fromName}</a> ${!isAction ? ': ' : ''}${message}`;
    div.dataset.timestamp = timestamp;
    const test =
        messagesContainer.scrollTop >
        messagesContainer.scrollHeight - messagesContainer.clientHeight - 50;
    messagesContainer.appendChild(div);
    if (test) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    gracefulDegrade(div);
    if (globalsettings.sound_im) Sound.play('imbeep');
}

/* global RUN, globalsettings */

import Sound from '../sound';
import { flashTitle } from './flashing-title';
import gracefulDegrade from './graceful-degrade';
import Window from './window';

function notification(fromName: string, message: string) {
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

function createMessagingWindow({
    fromId,
    fromName,
    message,
}: {
    fromId: number;
    fromName: string;
    message: string;
}) {
    let messagesContainer: HTMLDivElement | null = document.querySelector(
        `#im_${fromId} .ims`,
    );

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
        `.replaceAll(/%s/, `${fromId}`),
        className: 'im',
        resize: '.ims',
        animate: true,
        id: `im_${fromId}`,
    });

    const win = imWindow.create();
    gracefulDegrade(win);
    const focus = () => {
        win.querySelector('form')?.im_im.focus();
    };
    win.onclick = focus;
    focus();
    messagesContainer = document.querySelector(`#im_${fromId} .ims`)!;

    const test = getComputedStyle(messagesContainer);
    messagesContainer.style.width = test.width;
    messagesContainer.style.height = test.height;
    if (message && globalsettings.sound_im) Sound.play('imnewwindow');

    return messagesContainer;
}

export default function IMWindow(uid: number, uname: string) {
    if (!globalsettings.can_im) {
        // eslint-disable-next-line no-alert
        alert('You do not have permission to use this feature.');
        return;
    }

    RUN.stream.commands.im(uid, uname, '');
}

export function messageReceived({
    fromId,
    fromName,
    message,
    fromMe,
    timestamp,
}: {
    fromId: number;
    fromName: string;
    message: string;
    fromMe: number;
    timestamp: number;
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
        document.querySelector(`#im_${fromId}`)?.classList.remove('offline');
    }
    div.innerHTML = `<a href='?act=vu${
        fromMe || fromId
    }' class='name'>${fromName}</a> ${!isAction ? ': ' : ''}${message}`;
    div.dataset.timestamp = `${timestamp}`;
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

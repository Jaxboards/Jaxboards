/* global globalSettings */

import commands from "../RUN/commands";
import Sound from "../sound";
import { flashTitle } from "./flashing-title";
import gracefulDegrade from "./graceful-degrade";
import Window from "./window";

function notification(fromName: string, message: string) {
  flashTitle(`New message from ${fromName}!`);

  if (
    !document.hasFocus() &&
    globalThis.Notification &&
    Notification.permission === "granted"
  ) {
    const notify = new Notification(`${fromName} says:`, {
      body: message,
    });
    notify.onclick = () => {
      globalThis.focus();
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
                    <input type='hidden' name='im_uid' value='%s'>
                    <input type='text' name='im_im' autocomplete='off'>
                </form>
            </div>
        `.replaceAll("%s", `${fromId}`),
    className: "im",
    resize: ".ims",
    animate: true,
    id: `im_${fromId}`,
  });

  // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
  const win = imWindow.element!;

  gracefulDegrade(win);

  const focus = () => {
    win.querySelector<HTMLInputElement>('form input[name="im_im"]')?.focus();
  };
  win.addEventListener("click", focus);
  focus();

  // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
  messagesContainer = win.querySelector(".ims")!;

  const test = getComputedStyle(messagesContainer);
  messagesContainer.style.width = test.width;
  messagesContainer.style.height = test.height;
  if (message && globalSettings.soundIM) Sound.play("imnewwindow");

  return messagesContainer;
}

export default function IMWindow(uid: number, uname: string) {
  if (!globalSettings.canIM) {
    alert("You do not have permission to use this feature.");
    return;
  }

  commands.im(uid, uname, "", 1, Date.now());
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

  const div = document.createElement("div");
  const isAction = message.startsWith("/me");
  if (isAction) {
    div.className = "action";
    /* eslint-disable no-param-reassign */
    message = message.substring(3);
    fromName = `***${fromName}`;
    /* eslint-enable no-param-reassign */
  }
  div.classList.add(fromMe ? "you" : "them");
  if (!fromMe) {
    document.querySelector(`#im_${fromId}`)?.classList.remove("offline");
  }
  div.innerHTML = `<a href='/profile/${
    fromMe || fromId
  }' class='name'>${fromName}</a> ${isAction ? "" : ": "}${message}`;
  div.dataset.timestamp = `${timestamp}`;
  const test =
    messagesContainer.scrollTop >
    messagesContainer.scrollHeight - messagesContainer.clientHeight - 50;
  messagesContainer.appendChild(div);
  if (test) {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }
  gracefulDegrade(div);
  if (globalSettings.soundIM) Sound.play("imbeep");
}

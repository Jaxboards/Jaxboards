import Window from "./window";

function openShortcutsWindow() {
  const win = new Window({
    title: "Keyboard Shortcuts",
    id: "shortcuts",
    content:
      `
      <style>
        #shortcuts { width: 300px; }
        #shortcuts dt { float: left;margin-left: 5px;}
      </style>
      <h3>Navigation</h3>
      <dl>
        <dt>/</dt><dd>This help window</dd>
        <dt>i</dt><dd>Inbox</dd>
        <dt>s</dt><dd>Settings</dd>
        ` +
      (globalSettings.isAdmin ? `<dt>a</dt><dd>Admin CP</dd>` : "") +
      (globalSettings.isMod ? `<dt>m</dt><dd>Moderator CP</dd>` : "") +
      `</dl>

      <h3>Inbox</h3>
      <dl>
        <dt>c</dt><dd>Compose</dd>
        <dt>i</dt><dd>Inbox</dd>
        <dt>t</dt><dd>Ticker</dd>
      </dl>

      <h3>Forum/Topic</h3>
      <dl>
        <dt>t</dt><dd>New Topic</dd>
        <dt>r</dt><dd>Reply</dd>
        <dt>p</dt><dd>Full Reply</dd>
      </dl>
      `,
  });

  win.create();
}

export function handleKeyboardShortcuts(event: KeyboardEvent) {
  // ignore input events
  if (
    event.target instanceof HTMLInputElement ||
    event.target instanceof HTMLTextAreaElement ||
    event.target instanceof HTMLSelectElement
  ) {
    return;
  }

  if (event.key === "/") {
    openShortcutsWindow();
    return;
  }

  const shortcutElement = document.documentElement.querySelector<HTMLElement>(
    `[data-shortcut="${event.key}"]`,
  );

  shortcutElement?.click();
}

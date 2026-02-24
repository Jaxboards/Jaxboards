/* global globalSettings */

import { bbcodeToHTML, htmlToBBCode } from "../JAX/bbcode-utils";
import Browser from "../JAX/browser";
import register, { Component } from "../JAX/component";
import { getComputedStyle, getHighestZIndex, toDOM } from "../JAX/dom";
import { replaceSelection } from "../JAX/selection";

const URL_REGEX = /^(ht|f)tps?:\/\/[\w.\-%&?=/]+$/;
const isURL = (text: string) => URL_REGEX.test(text);

const webSafeFonts: Record<string, string[]> = {
  "Sans Serif": [
    "Arial",
    "Arial Black",
    "Arial Narrow",
    "Arial Rounded MT Bold",
    "Avant Garde",
    "Calibri",
    "Candara",
    "Century Gothic",
    "Comic Sans MS", // Not technically web safe but can't not have it
    "Franklin Gothic Medium",
    "Futura",
    "Geneva",
    "Gill Sans",
    "Helvetica",
    "Impact",
    "Lucida Grande",
    "Optima",
    "Segoe UI",
    "Tahoma",
    "Trebuchet MS",
    "Verdana",
  ],
  Serif: [
    "Big Caslon",
    "Bodoni MT",
    "Book Antiqua",
    "Calisto MT",
    "Cambria",
    "Didot",
    "Garamond",
    "Georgia",
    "Goudy Old Style",
    "Hoefler Text",
    "Lucida Bright",
    "Palatino",
    "Perpetua",
    "Rockwell",
    "Rockwell Extra Bold",
    "Baskerville",
    "Times New Roman",
  ],
  Monospaced: [
    "Consolas",
    "Courier New",
    "Lucida Console",
    "Lucida Sans Typewriter",
    "Monaco",
    "Andale Mono",
  ],
  Fantasy: ["Copperplate", "Papyrus"],
  Script: ["Brush Script MT"],
};

export default class Editor extends Component<HTMLTextAreaElement> {
  iframe: HTMLIFrameElement;

  colorWindow?: HTMLDivElement;

  container: HTMLDivElement;

  htmlMode = true;

  editbar?: HTMLDivElement;

  emoteWindow?: HTMLDivElement;

  static hydrate(container: HTMLElement): void {
    register(
      "Editor",
      container.querySelectorAll<HTMLTextAreaElement>("textarea.bbcode-editor"),
      this,
    );
  }

  constructor(element: HTMLTextAreaElement) {
    super(element);

    // Create DOM
    this.container = Object.assign(document.createElement("div"), {
      className: "editor",
    });
    this.iframe = document.createElement("iframe");
    this.iframe.style.display = "none";

    // Attach Event Listeners
    this.iframe.addEventListener("load", () => this.iframeLoaded(), {
      once: true,
    });

    // Update DOM
    element.before(this.container);
    this.container.appendChild(element);
    this.container.appendChild(this.iframe);
  }

  get doc() {
    return this.window?.document;
  }

  get window() {
    return this.iframe.contentWindow;
  }

  iframeLoaded() {
    const { iframe, element } = this;

    iframe.className = "editorframe";

    this.htmlMode = globalSettings.wysiwyg;

    // sync textarea -> editor
    element.addEventListener("input", () => {
      this.setSource(bbcodeToHTML(element.value));
    });

    // sync editor -> textarea
    this.doc?.addEventListener("input", () => {
      if (!this.doc) return;
      // keep textarea updated with BBCode in real time
      this.element.value = htmlToBBCode(this.doc.body);
    });
    this.doc?.addEventListener("paste", (event) => {
      event.preventDefault();

      const text = event.clipboardData?.getData("text/plain");

      this.doc?.execCommand("insertText", false, text);
    });
    this.doc?.addEventListener("drop", (event) => {
      if (!event.dataTransfer?.files.length) {
        return;
      }
      this.upload(event.dataTransfer.files[0]);
    });
    this.doc?.addEventListener("dragleave", () => {
      this.getAttachmentStatus().remove();
    });
    this.doc?.addEventListener("dragover", () => {
      this.getAttachmentStatus();
    });

    if (!this.doc) {
      return;
    }

    const cs = getComputedStyle(element);
    const body = this.doc.getElementsByTagName("body")[0];
    if (body && cs) {
      body.style.backgroundColor = cs.backgroundColor;
      body.style.color = cs.color;
      body.style.borderColor = "#FFF";
    }

    this.doc.designMode = "on";

    this.editbar = this.buildEditBar();

    iframe.style.height = `${element.clientHeight}px`;

    element.before(this.editbar);

    // Set the source and initialize the editor
    this.setSource("<div></div>");
    setTimeout(() => {
      this.setSource(bbcodeToHTML(element.value));
      this.switchMode(this.htmlMode);

      if (element.hasAttribute("autofocus")) {
        this.window?.focus();
      }
    }, 100);
  }

  getAttachmentStatus(): HTMLDivElement {
    let attachment =
      this.doc?.querySelector<HTMLDivElement>("#attachmentStatus");
    if (attachment) {
      return attachment;
    }

    attachment = document.createElement("div");
    Object.assign(attachment, {
      id: "attachmentStatus",
      innerHTML: "Drop file here",
      contentEditable: "false",
    });
    Object.assign(attachment.style, {
      position: "fixed",
      padding: "20px",
      top: "50%",
      left: "50%",
      translate: "-50% -50%",
      border: "2px dashed #ccc",
    });
    this.doc?.body.appendChild(attachment);
    return attachment;
  }

  upload(file: File) {
    const formData = new FormData();
    formData.append("Filedata", file);

    const xhr = new XMLHttpRequest();

    this.getAttachmentStatus().innerHTML =
      'Uploading: <progress id="attachmentProgress"></div>';
    const progressBar =
      this.getAttachmentStatus().querySelector<HTMLProgressElement>(
        "#attachmentProgress",
      );

    // 1. Attach the progress event listener to the xhr.upload object
    xhr.upload.addEventListener("progress", (event) => {
      if (progressBar && event.lengthComputable) {
        // Calculate the percentage
        const percentComplete = Math.round((event.loaded / event.total) * 100);

        // Update the UI
        progressBar.value = event.loaded;
        progressBar.max = event.total;
        progressBar.textContent = `${percentComplete}%`;
      }
    });

    // 2. Handle completion
    xhr.addEventListener("load", () => {
      this.window?.focus();
      this.getAttachmentStatus().remove();

      const response = xhr.responseText;
      if (/\D/.test(response)) {
        alert("Error: " + response);
        return;
      }

      this.cmd("inserthtml", `[attachment]${xhr.responseText}[/attachment]`);
    });

    // 3. Handle errors (optional)
    xhr.upload.addEventListener("error", () => {
      this.getAttachmentStatus().remove();
    });

    // 4. Open and send the request (POST method and a server URL needed)
    xhr.open("POST", "/api/upload", true);
    xhr.send(formData);
  }

  buildEditBar(): HTMLDivElement {
    const editbar = document.createElement("div");
    editbar.className = "editbar";
    editbar.innerHTML = [
      `<select class="fontface">
        <option value="">Default Font</option>
        <optgroup label="Headings">
          <option value="heading_1" style="font-weight:bold;font-size: 2em">Heading 1</option>
          <option value="heading_2" style="font-weight:bold;font-size: 1.5em">Heading 2</option>
          <option value="heading_3" style="font-weight:bold;font-size: 1.17em">Heading 3</option>
          <option value="heading_4" style="font-weight:bold;font-size: 1em">Heading 4</option>
          <option value="heading_5" style="font-weight:bold;font-size: 0.83em">Heading 5</option>
          <option value="heading_6" style="font-weight:bold;font-size: 0.67em">Heading 6</option>
        </optgroup>
      </select>`,
      `<a class="bold" title="Bold"></a>`,
      `<a class="italic" title="Italic"></a>`,
      `<a class="underline" title="Underline"></a>`,
      `<a class="strikethrough" title="Strike-Through"></a>`,
      `<a class="forecolor" title="Foreground Color"></a>`,
      `<a class="backcolor" title="Background Color"></a>`,
      `<a class="c_smileys" title="Insert Emoticon"></a>`,
      `<a class="insertimage" title="Insert Image"></a>`,
      `<a class="createlink" title="Insert Link"></a>`,
      `<a class="c_email" title="Insert Email"></a>`,
      `<a class="justifyleft" title="Align Left"></a>`,
      `<a class="justifycenter" title="Center"></a>`,
      `<a class="justifyright" title="Align Right"></a>`,
      `<a class="c_youtube" title="Insert video from any of your favorite video services!"></a>`,
      `<a class="c_code" title="Insert code"></a>`,
      `<a class="c_quote" title="Insert Quote"></a>`,
      `<a class="c_spoiler" title="Insert Spoiler"></a>`,
      `<a class="insertorderedlist" title="Create Ordered List"></a>`,
      `<a class="insertunorderedlist" title="Create Unordered List"></a>`,
      `<a class="c_switcheditmode" title="Switch editor mode"></a>`,
    ].join("");

    editbar.addEventListener("click", (evt) => {
      if (
        evt.target instanceof HTMLElement &&
        evt.target.matches(".editbar a")
      ) {
        this.editbarCommand(evt, evt.target.className);
      }
    });

    const fontFace =
      editbar.querySelector<HTMLSelectElement>("select.fontface");
    if (fontFace) {
      fontFace.addEventListener("input", (evt) => {
        const value = fontFace.value;
        if (value.startsWith("heading_")) {
          this.editbarCommand(
            evt as MouseEvent,
            "heading",
            value.replace("heading_", "h"),
          );
        } else if (value) {
          this.editbarCommand(evt as MouseEvent, "fontname", value);
        }
      });
      for (const group of Object.keys(webSafeFonts)) {
        const options = webSafeFonts[group].map(
          (font: string) =>
            `<option style="font-family: ${font}">${font}</option>`,
        );
        fontFace.innerHTML += `<optgroup label="${group}">${options}</optgroup>`;
      }
    }

    return editbar;
  }

  editbarCommand(event: MouseEvent, cmd: string, cmdValue?: string) {
    event.preventDefault();
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    switch (cmd) {
      case "forecolor":
      case "backcolor":
        this.showColors(
          target.offsetLeft,
          target.offsetTop + target.offsetHeight,
          cmd,
        );
        break;
      case "c_smileys":
        void this.showEmotes(
          target.offsetLeft,
          target.offsetTop + target.offsetHeight,
        );
        break;
      case "c_switcheditmode":
        this.switchMode(!this.htmlMode);
        break;
      default:
        this.cmd(cmd, cmdValue);
        break;
    }
  }

  async showEmotes(x: number, y: number) {
    const emotewin = this.emoteWindow;
    if (!emotewin) {
      const res = await fetch("/api/emotes");
      const json = (await res.json()) as [string[], string[]];
      if (res.ok) {
        this.createEmoteWindow(json, { x, y });
      }
      return;
    }
    if (emotewin.style.display === "none") {
      emotewin.style.display = "";
      emotewin.style.left = `${x}px`;
      emotewin.style.top = `${y}px`;
    } else {
      this.hideEmotes();
    }
  }

  hideEmotes() {
    if (this.emoteWindow) {
      this.emoteWindow.style.display = "none";
    }
  }

  createEmoteWindow(
    [smileyText, images]: [string[], string[]],
    position: { x: number; y: number },
  ) {
    const emotewin = document.createElement("div");
    emotewin.className = "emotewin";
    emotewin.addEventListener("click", (event) => {
      if (!(event.target instanceof HTMLElement)) return;

      const button = event.target.closest("button");
      if (!button) return;

      const img = button.querySelector("img");
      if (!img) return;
      this.cmd("inserthtml", this.htmlMode ? img.outerHTML : img.dataset.emoji);
      this.hideEmotes();
    });

    smileyText.forEach((smiley: string, i: number) => {
      const image = images[i];
      const link = Object.assign(document.createElement("button"), {
        type: "button",
        innerHTML: `${image} ${smiley}`,
      });
      emotewin.append(link);
    });

    Object.assign(emotewin.style, {
      position: "absolute",
      display: "none",
      zIndex: getHighestZIndex(),
    });

    this.emoteWindow = emotewin;
    this.container.append(emotewin);
    void this.showEmotes(position.x, position.y);
  }

  colorHandler(cmd: string, color: string) {
    this.cmd(cmd, color);
    this.hideColors();
  }

  showColors(posx: number, posy: number, cmd: string) {
    // close the color window if it is already open
    this.hideColors();
    const colors = [
      "#FFFFFF",
      "#AAAAAA",
      "#000000",
      "#FF0000",
      "#00FF00",
      "#0000FF",
      "#FFFF00",
      "#00FFFF",
      "#FF00FF",
    ];

    const colorwin = toDOM<HTMLDivElement>(`
      <div>
        ${colors.map((color) => `<button style="background-color:${color}" data-color="${color}"></button>`).join("")}
      </div>
    `);

    Object.assign(colorwin.style, {
      display: "grid",
      gridTemplateColumns: "20px 20px 20px",
      gridTemplateRows: "20px 20px 20px",

      position: "absolute",
      top: `${posy}px`,
      left: `${posx}px`,

      cursor: "pointer",
      zIndex: getHighestZIndex(),
    });

    colorwin.addEventListener("click", (event: PointerEvent) => {
      if (
        event.target instanceof HTMLButtonElement &&
        event.target.dataset.color
      ) {
        this.colorHandler(cmd, event.target.dataset.color);
      }
    });

    this.colorWindow = colorwin;
    this.container.append(colorwin);
    return null;
  }

  hideColors() {
    if (this.colorWindow) {
      this.colorWindow.remove();
      this.colorWindow = undefined;
    }
  }

  cmd(command: string, arg?: string) {
    const selection = this.getSelection();
    let bbcode = "";

    const execCommandArgs: Parameters<Document["execCommand"]> = [
      command,
      false,
      arg,
    ];

    switch (command.toLowerCase()) {
      case "bold": {
        bbcode = `[b]${selection}[/b]`;
        break;
      }

      case "italic": {
        bbcode = `[i]${selection}[/i]`;
        break;
      }

      case "underline": {
        bbcode = `[u]${selection}[/u]`;
        break;
      }

      case "strikethrough": {
        bbcode = `[s]${selection}[/s]`;
        break;
      }

      case "justifyright": {
        bbcode = `[align=right]${selection}[/align]`;
        break;
      }

      case "justifycenter": {
        bbcode = `[align=center]${selection}[/align]`;
        break;
      }

      case "justifyleft": {
        bbcode = `[align=left]${selection}[/align]`;
        break;
      }

      case "insertimage": {
        const src = prompt("Image URL:") || "";
        if (!src) {
          return;
        }
        if (!isURL(src)) {
          alert("Please enter a valid URL.");
          return;
        }
        const alt = prompt("How would you describe this image?") || "";
        if (!alt) {
          alert("Please enter description for image.");
          return;
        }

        const element = new Image();
        element.alt = alt;
        element.src = src;

        bbcode = `[img=${alt}]${src}[/img]`;
        execCommandArgs[0] = "insertHTML";
        execCommandArgs[2] = element.outerHTML;
        break;
      }

      case "insertorderedlist":
        if (!this.htmlMode) {
          bbcode = `[ol]${selection.replaceAll(/(.+([\r\n]+|$))/i, "* $1")}[/ol]`;
        }
        break;

      case "insertunorderedlist":
        if (!this.htmlMode) {
          bbcode = `[ul]${selection.replaceAll(/(.+([\r\n]+|$))/i, "* $1")}[/ul]`;
        }
        break;

      case "createlink": {
        let link = prompt("Link:") || "";
        if (!link) return;
        if (!/^(https?|ftp|mailto):/.test(link)) link = `https://${arg}`;

        bbcode = `[url=${link}]${selection}[/url]`;
        execCommandArgs[2] = link;
        break;
      }

      case "c_email": {
        let email = prompt("Email:") || "";
        if (!email) return;

        email = `mailto:${email}`;

        bbcode = `[url=${email}]${selection}[/url]`;
        execCommandArgs[0] = "createlink";
        execCommandArgs[2] = email;
        break;
      }

      case "backcolor": {
        if (Browser.firefox || Browser.safari) {
          execCommandArgs[0] = "hilitecolor";
        }
        bbcode = `[bgcolor=${arg}]${selection}[/bgcolor]`;
        break;
      }

      case "forecolor": {
        bbcode = `[color=${arg}]${selection}[/color]`;
        break;
      }

      case "fontname": {
        bbcode = `[font=${arg}]${selection}[/font]`;
        break;
      }

      case "c_code": {
        bbcode = `[code]${selection}[/code]`;
        execCommandArgs[0] = "inserthtml";
        execCommandArgs[2] = bbcode;
        break;
      }

      case "c_quote": {
        const quotee = prompt("Who said this?") || "";

        bbcode = `[quote${quotee ? `=${quotee}` : ""}]${selection}[/quote]`;
        execCommandArgs[0] = "inserthtml";
        execCommandArgs[2] = bbcode;
        break;
      }

      case "c_spoiler": {
        bbcode = `[spoiler]${selection}[/spoiler]`;
        execCommandArgs[0] = "inserthtml";
        execCommandArgs[2] = bbcode;
        break;
      }

      case "c_youtube": {
        const url = prompt("Video URL?") || "";
        if (!url) {
          return;
        }

        bbcode = `[video]${url}[/video]`;
        execCommandArgs[0] = "inserthtml";
        execCommandArgs[2] = bbcode;
        break;
      }

      case "inserthtml": {
        bbcode = arg || "";
        break;
      }

      case "heading": {
        bbcode = `[${arg}]${selection}[/${arg}]`;

        // Chrome doesn't support 'heading' so switch to formatBlock
        if (Browser.chrome) {
          execCommandArgs[0] = "formatBlock";
          execCommandArgs[2] = `<${arg}>`;
        }
        break;
      }

      default:
        throw new Error(`Unsupported editor command ${command}`);
    }

    if (this.htmlMode) {
      this.doc?.execCommand(...execCommandArgs);
      this.doc?.dispatchEvent(new Event("input"));
      this.window?.focus();
    } else {
      replaceSelection(this.element, bbcode);
      this.element.dispatchEvent(new Event("input"));
    }
  }

  getSelection() {
    if (this.htmlMode) {
      return this.window?.getSelection()?.toString() || "";
    }
    return this.element.value.substring(
      this.element.selectionStart,
      this.element.selectionEnd,
    );
  }

  getSource() {
    return this.doc?.body.innerHTML;
  }

  setSource(innerHTML: string) {
    if (this.doc) this.doc.body.innerHTML = innerHTML;
  }

  switchMode(htmlMode: boolean) {
    const { element, iframe } = this;
    if (htmlMode) {
      element.style.display = "none";
      iframe.style.display = "";
    } else {
      element.style.display = "";
      iframe.style.display = "none";
    }
    this.htmlMode = htmlMode;
  }
}

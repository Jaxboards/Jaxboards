/* global globalSettings */

import { bbcodeToHTML, htmlToBBCode } from '../JAX/bbcode-utils';
import Browser from '../JAX/browser';
import register, { Component } from '../JAX/component';
import { getComputedStyle, insertBefore } from '../JAX/el';
import { replaceSelection } from '../JAX/selection';

const URL_REGEX = /^(ht|f)tps?:\/\/[\w.\-%&?=/]+$/;
const isURL = (text: string) => URL_REGEX.test(text);

const webSafeFonts: Record<string, string[]> = {
    'Sans Serif': [
        'Arial',
        'Arial Black',
        'Arial Narrow',
        'Arial Rounded MT Bold',
        'Avant Garde',
        'Calibri',
        'Candara',
        'Century Gothic',
        'Comic Sans MS', // Not technically web safe but can't not have it
        'Franklin Gothic Medium',
        'Futura',
        'Geneva',
        'Gill Sans',
        'Helvetica',
        'Impact',
        'Lucida Grande',
        'Optima',
        'Segoe UI',
        'Tahoma',
        'Trebuchet MS',
        'Verdana',
    ],
    Serif: [
        'Big Caslon',
        'Bodoni MT',
        'Book Antiqua',
        'Calisto MT',
        'Cambria',
        'Didot',
        'Garamond',
        'Georgia',
        'Goudy Old Style',
        'Hoefler Text',
        'Lucida Bright',
        'Palatino',
        'Perpetua',
        'Rockwell',
        'Rockwell Extra Bold',
        'Baskerville',
        'Times New Roman',
    ],
    Monospaced: [
        'Consolas',
        'Courier New',
        'Lucida Console',
        'Lucida Sans Typewriter',
        'Monaco',
        'Andale Mono',
    ],
    Fantasy: ['Copperplate', 'Papyrus'],
    Script: ['Brush Script MT'],
};

export default class Editor extends Component<HTMLTextAreaElement> {
    iframe: HTMLIFrameElement;

    colorWindow?: HTMLTableElement;

    container: HTMLDivElement;

    htmlMode = true;

    editbar?: HTMLDivElement;

    emoteWindow?: HTMLDivElement;

    static hydrate(container: HTMLElement): void {
        register(
            'Editor',
            container.querySelectorAll<HTMLTextAreaElement>(
                'textarea.bbcode-editor',
            ),
            this,
        );
    }

    constructor(element: HTMLTextAreaElement) {
        super(element);

        // Create DOM
        this.container = document.createElement('div');
        this.iframe = document.createElement('iframe');
        this.iframe.style.display = 'none';

        // Attach Event Listeners
        this.iframe.addEventListener('load', () => this.iframeLoaded(), {
            once: true,
        });

        // Update DOM
        insertBefore(this.container, element);
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

        iframe.className = 'editorframe';

        this.htmlMode = globalSettings.wysiwyg;

        this.doc?.addEventListener('input', () => {
            // keep textarea updated with BBCode in real time
            this.element.value = htmlToBBCode(this.doc.body);
        });

        if (!this.doc) {
            return;
        }

        const cs = getComputedStyle(element);
        const body = this.doc.getElementsByTagName('body')[0];
        if (body && cs) {
            body.style.backgroundColor = cs.backgroundColor;
            body.style.color = cs.color;
            body.style.borderColor = '#FFF';
        }

        this.doc.designMode = 'on';

        this.editbar = this.buildEditBar();

        iframe.style.height = `${element.clientHeight}px`;

        insertBefore(this.editbar, element);

        // Set the source and initialize the editor
        this.setSource('<div></div>');
        setTimeout(() => {
            this.setSource(bbcodeToHTML(element.value));
            this.switchMode(this.htmlMode);
        }, 100);
    }

    buildEditBar(): HTMLDivElement {
        const editbar = document.createElement('div');
        editbar.className = 'editbar';
        editbar.innerHTML = [
            `<select class="fontface"><option>Default Font</option></select>`,
            `<a class="bold" title="Bold"></a>`,
            `<a class="italic" title="Italic"></a>`,
            `<a class="underline" title="Underline"></a>`,
            `<a class="strikethrough" title="Strike-Through"></a>`,
            `<a class="forecolor" title="Foreground Color"></a>`,
            `<a class="backcolor" title="Background Color"></a>`,
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
            `<a class="c_smileys" title="Insert Emoticon"></a>`,
            `<a class="c_switcheditmode" title="Switch editor mode"></a>`,
        ].join('');

        editbar.addEventListener('click', (evt) => {
            if (
                evt.target instanceof HTMLElement &&
                evt.target.matches('.editbar a')
            ) {
                this.editbarCommand(evt, evt.target.className);
            }
        });

        const fontFace =
            editbar.querySelector<HTMLSelectElement>('select.fontface');
        if (fontFace) {
            fontFace.addEventListener('input', (evt) => {
                this.editbarCommand(
                    evt as MouseEvent,
                    'fontname',
                    fontFace.value,
                );
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

        switch (cmd) {
            case 'forecolor':
            case 'backcolor':
                this.showColors(event.pageX, event.pageY, cmd);
                break;
            case 'c_smileys':
                void this.showEmotes(event.pageX, event.pageY);
                break;
            case 'c_switcheditmode':
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
            const res = await fetch('/api/emotes');
            const json = (await res.json()) as [string[], string[]];
            if (res.ok) {
                this.createEmoteWindow(json, { x, y });
            }
            return;
        }
        if (emotewin.style.display === 'none') {
            emotewin.style.display = '';
            emotewin.style.left = `${x}px`;
            emotewin.style.top = `${y}px`;
        } else {
            this.hideEmotes();
        }
    }

    hideEmotes() {
        if (this.emoteWindow) {
            this.emoteWindow.style.display = 'none';
        }
    }

    createEmoteWindow(
        [smileyText, images]: [string[], string[]],
        position: { x: number; y: number },
    ) {
        const emotewin = document.createElement('div');
        emotewin.className = 'emotewin';

        smileyText.forEach((smiley: string, i: number) => {
            const image = images[i];
            const link = document.createElement('a');
            link.href = 'javascript:void(0)';
            link.addEventListener('click', () => {
                this.cmd('inserthtml', image);
                this.hideEmotes();
            });
            link.innerHTML = `${image} ${smiley}`;
            emotewin.appendChild(link);
        });

        emotewin.style.position = 'absolute';
        emotewin.style.display = 'none';
        this.emoteWindow = emotewin;
        document.querySelector('#page')?.appendChild(emotewin);
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
            '#FFFFFF',
            '#AAAAAA',
            '#000000',
            '#FF0000',
            '#00FF00',
            '#0000FF',
            '#FFFF00',
            '#00FFFF',
            '#FF00FF',
        ];
        const l = colors.length;
        const sq = Math.ceil(Math.sqrt(l));

        const colorwin = document.createElement('table');
        Object.assign(colorwin.style, {
            borderCollapse: 'collapse',
            position: 'absolute',
            top: `${posy}px`,
            left: `${posx}px`,
        });

        for (let y = 0; y < sq; y += 1) {
            const r = colorwin.insertRow(y);
            for (let x = 0; x < sq; x += 1) {
                const c = r.insertCell(x);
                const color = colors[x + y * sq];
                if (!color) {
                    continue;
                }
                c.style.border = '1px solid #000';
                c.style.padding = '0px';
                const a = document.createElement('a');
                a.href = 'javascript:void(0)';
                a.addEventListener('click', () =>
                    this.colorHandler(cmd, color),
                );
                c.appendChild(a);
                Object.assign(a.style, {
                    display: 'block',
                    backgroundColor: color,
                    height: '20px',
                    width: '20px',
                    margin: 0,
                });
            }
        }
        this.colorWindow = colorwin;
        document.querySelector('#page')?.appendChild(colorwin);
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
        let bbcode = '';
        let realCommand = command;
        let arg1 = arg;
        switch (command.toLowerCase()) {
            case 'bold':
                bbcode = `[b]${selection}[/b]`;
                break;
            case 'italic':
                bbcode = `[i]${selection}[/i]`;
                break;
            case 'underline':
                bbcode = `[u]${selection}[/u]`;
                break;
            case 'strikethrough':
                bbcode = `[s]${selection}[/s]`;
                break;
            case 'justifyright':
                bbcode = `[align=right]${selection}[/align]`;
                break;
            case 'justifycenter':
                bbcode = `[align=center]${selection}[/align]`;
                break;
            case 'justifyleft':
                bbcode = `[align=left]${selection}[/align]`;
                break;
            case 'insertimage':
                arg1 = prompt('Image URL:') || '';
                if (!arg1) {
                    return;
                }
                if (!isURL(arg1)) {
                    alert('Please enter a valid URL.');
                    return;
                }
                bbcode = `[img]${arg1}[/img]`;
                break;
            case 'insertorderedlist':
                if (!this.htmlMode) {
                    bbcode = `[ol]${selection.replaceAll(/(.+([\r\n]+|$))/i, '*$1')}[/ol]`;
                }
                break;
            case 'insertunorderedlist':
                if (!this.htmlMode) {
                    bbcode = `[ul]${selection.replaceAll(/(.+([\r\n]+|$))/i, '*$1')}[/ul]`;
                }
                break;
            case 'createlink':
                arg1 = prompt('Link:') || '';
                if (!arg1) return;
                if (!/^(https?|ftp|mailto):/.test(arg1))
                    arg1 = `https://${arg1}`;
                bbcode = `[url=${arg1}]${selection}[/url]`;
                break;
            case 'c_email':
                arg1 = prompt('Email:') || '';
                if (!arg1) return;
                realCommand = 'createlink';
                arg1 = `mailto:${arg1}`;
                bbcode = `[url=${arg1}]${selection}[/url]`;
                break;
            case 'backcolor':
                if (Browser.firefox || Browser.safari) {
                    realCommand = 'hilitecolor';
                }
                bbcode = `[bgcolor=${arg1}]${selection}[/bgcolor]`;
                break;
            case 'forecolor':
                bbcode = `[color=${arg1}]${selection}[/color]`;
                break;
            case 'fontname':
                bbcode = `[font=${arg1}]${selection}[/font]`;
                break;
            case 'c_code':
                realCommand = 'inserthtml';
                arg1 = `[code]${selection}[/code]`;
                bbcode = arg1;
                break;
            case 'c_quote':
                realCommand = 'inserthtml';
                arg1 = prompt('Who said this?') || '';
                arg1 = arg1 ? `=${arg1}` : '';
                arg1 = `[quote${arg1}]${selection}[/quote]`;
                bbcode = arg1;
                break;
            case 'c_spoiler':
                realCommand = 'inserthtml';
                arg1 = `[spoiler]${selection}[/spoiler]`;
                bbcode = arg1;
                break;
            case 'c_youtube':
                realCommand = 'inserthtml';
                arg1 = prompt('Video URL?') || '';
                if (!arg1) {
                    return;
                }
                arg1 = `[video]${arg1}[/video]`;
                bbcode = arg1;
                break;
            case 'inserthtml':
                bbcode = arg1 || '';
                break;
            default:
                throw new Error(`Unsupported editor command ${command}`);
        }
        if (this.htmlMode) {
            this.doc?.execCommand(realCommand, false, arg1);
            this.window?.focus();
        } else replaceSelection(this.element, bbcode);
    }

    getSelection() {
        if (this.htmlMode) {
            return this.window?.getSelection()?.toString() || '';
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
            this.setSource(bbcodeToHTML(element.value));
            element.style.display = 'none';
            iframe.style.display = '';
        } else {
            element.style.display = '';
            iframe.style.display = 'none';
        }
        this.htmlMode = htmlMode;
    }
}

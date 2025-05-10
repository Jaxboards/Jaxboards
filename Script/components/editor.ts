/* global globalsettings */
/* eslint-disable no-script-url, no-alert */

import Component from '../classes/component';
import Ajax from '../JAX/ajax';
import { bbcodeToHTML, htmlToBBCode } from '../JAX/bbcode-utils';
import Browser from '../JAX/browser';
import { getComputedStyle, insertAfter, insertBefore } from '../JAX/el';
import { replaceSelection } from '../JAX/selection';

const URL_REGEX = /^(ht|f)tps?:\/\/[\w.\-%&?=/]+$/;
const isURL = (text: string) => URL_REGEX.test(text);

export default class Editor extends Component {
    iframe: HTMLIFrameElement;

    element: HTMLTextAreaElement;

    static get selector() {
        return 'textarea.bbcode-editor';
    }

    constructor(element: HTMLTextAreaElement) {
        super(element);
        this.element = element;

        this.iframe = document.createElement('iframe');
        this.iframe.addEventListener('load', () => this.iframeLoaded());
        this.iframe.style.display = 'none';
        insertAfter(this.iframe, element);

        element.closest('form')?.addEventListener('submit', () => {
            this.submit();
        });
    }

    iframeLoaded() {
        const { iframe, element } = this;

        iframe.className = 'editorframe';
        // 1 for html editing mode, 0 for textarea mode
        this.mode = Browser.mobile || Browser.n3ds ? 0 : globalsettings.wysiwyg;
        this.mode = this.mode || 0;
        this.window = iframe.contentWindow;
        this.doc = iframe.contentWindow.document;

        const cs = getComputedStyle(element);
        const body = this.doc.getElementsByTagName('body')[0];
        if (body && cs) {
            body.style.backgroundColor = cs.backgroundColor;
            body.style.color = cs.color;
            body.style.borderColor = '#FFF';
        }

        this.doc.designMode = 'on';

        this.editbar = document.createElement('div');
        this.buildEditBar();

        iframe.style.height = `${element.clientHeight}px`;

        insertBefore(this.editbar, element);

        // Set the source and initialize the editor
        this.setSource('<div></div>');
        setTimeout(() => {
            this.setSource(bbcodeToHTML(element.value));
            this.switchMode(this.mode);
        }, 100);
    }

    buildEditBar() {
        this.editbar.className = 'editbar';
        const cmds = [
            'bold',
            'italic',
            'underline',
            'strikethrough',
            'forecolor',
            'backcolor',
            'insertimage',
            'createlink',
            'c_email',
            'justifyleft',
            'justifycenter',
            'justifyright',
            'c_youtube',
            'c_code',
            'c_quote',
            'c_spoiler',
            'insertorderedlist',
            'insertunorderedlist',
            'c_smileys',
            'c_switcheditmode',
        ];

        const cmddesc = [
            'Bold',
            'Italic',
            'Underline',
            'Strike-Through',
            'Foreground Color',
            'Background Color',
            'Insert Image',
            'Insert Link',
            'Insert email',
            'Align left',
            'Center',
            'Align right',
            'Insert video from any of your favorite video services!',
            'Insert code',
            'Insert Quote',
            'Insert Spoiler',
            'Create Ordered List',
            'Create Unordered List',
            'Insert Emoticon',
            'Switch editor mode',
        ];

        cmds.forEach((cmd, i) => {
            const a = document.createElement('a');
            a.className = cmd;
            a.title = cmddesc[i];
            a.href = 'javascript:void(0)';
            a.unselectable = 'on';
            a.onclick = (event) => this.editbarCommand(event, cmd);
            this.editbar.appendChild(a);
        });
    }

    editbarCommand(event, cmd) {
        event.preventDefault();

        switch (cmd) {
            case 'forecolor':
            case 'backcolor':
                this.showColors(event.pageX, event.pageY, cmd);
                break;
            case 'c_smileys':
                this.showEmotes(event.pageX, event.pageY);
                break;
            case 'c_switcheditmode':
                this.switchMode(Math.abs(this.mode - 1));
                break;
            default:
                this.cmd(cmd);
                break;
        }
    }

    showEmotes(x, y) {
        const emotewin = this.emoteWindow;
        if (!emotewin) {
            new Ajax().load('/api/?act=emotes', {
                callback: (response) =>
                    this.createEmoteWindow(response, { x, y }),
            });
            return;
        }
        if (emotewin.style.display === 'none') {
            emotewin.style.display = '';
            emotewin.style.top = `${y}px`;
            emotewin.style.left = `${x}px`;
        } else {
            this.hideEmotes();
        }
    }

    hideEmotes() {
        if (this.emoteWindow) {
            this.emoteWindow.style.display = 'none';
        }
    }

    createEmoteWindow(xml, position) {
        const [smileyText, images] = JSON.parse(xml.responseText);
        const emotewin = document.createElement('div');
        emotewin.className = 'emotewin';

        smileyText.forEach((smiley, i) => {
            const image = images[i];
            const link = document.createElement('a');
            link.href = 'javascript:void(0)';
            link.onclick = () => {
                this.cmd('inserthtml', image);
                this.hideEmotes();
            };
            link.innerHTML = `${image} ${smiley}`;
            emotewin.appendChild(link);
        });

        emotewin.style.position = 'absolute';
        emotewin.style.display = 'none';
        this.emoteWindow = emotewin;
        document.querySelector('#page').appendChild(emotewin);
        this.showEmotes(position.x, position.y);
    }

    colorHandler(cmd, color) {
        this.cmd(cmd, color);
        this.hideColors();
    }

    showColors(posx, posy, cmd) {
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
                    // eslint-disable-next-line no-continue
                    continue;
                }
                c.style.border = '1px solid #000';
                c.style.padding = 0;
                const a = document.createElement('a');
                a.href = 'javascript:void(0)';
                a.onclick = () => this.colorHandler(cmd, color);
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
        document.querySelector('#page').appendChild(colorwin);
        return null;
    }

    hideColors() {
        if (this.colorWindow) {
            this.colorWindow.parentNode.removeChild(this.colorWindow);
            this.colorWindow = undefined;
        }
    }

    cmd(command, arg) {
        let rng;
        const selection = this.getSelection();
        let bbcode;
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
                arg1 = prompt('Image URL:');
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
                if (!this.mode) {
                    bbcode = `[ol]${selection.replace(/(.+([\r\n]+|$))/gi, '*$1')}[/ol]`;
                }
                break;
            case 'insertunorderedlist':
                if (!this.mode) {
                    bbcode = `[ul]${selection.replace(/(.+([\r\n]+|$))/gi, '*$1')}[/ul]`;
                }
                break;
            case 'createlink':
                arg1 = prompt('Link:');
                if (!arg1) return;
                if (!arg1.match(/^(https?|ftp|mailto):/))
                    arg1 = `https://${arg1}`;
                bbcode = `[url=${arg1}]${selection}[/url]`;
                break;
            case 'c_email':
                arg1 = prompt('Email:');
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
            case 'c_code':
                realCommand = 'inserthtml';
                arg1 = `[code]${selection}[/code]`;
                bbcode = arg1;
                break;
            case 'c_quote':
                realCommand = 'inserthtml';
                arg1 = prompt('Who said this?');
                arg1 = `[quote${arg1 ? `=${arg1}` : ''}]${selection}[/quote]`;
                bbcode = arg1;
                break;
            case 'c_spoiler':
                realCommand = 'inserthtml';
                arg1 = `[spoiler]${selection}[/spoiler]`;
                bbcode = arg1;
                break;
            case 'c_youtube':
                realCommand = 'inserthtml';
                arg1 = prompt('Video URL?');
                if (!arg1) {
                    return;
                }
                arg1 = `[video]${arg1}[/video]`;
                bbcode = arg1;
                break;
            case 'inserthtml':
                bbcode = arg1;
                break;
            default:
                throw new Error(`Unsupported editor command ${command}`);
        }
        if (this.mode) {
            if (realCommand === 'inserthtml' && Browser.ie) {
                rng = this.doc.selection.createRange();
                if (!rng.text.length) this.doc.body.innerHTML += arg1;
                else {
                    rng.pasteHTML(arg1);
                    rng.collapse(false);
                    rng.select();
                }
            } else {
                this.doc.execCommand(realCommand, false, arg1 || false);
                if (this.iframe.contentWindow.focus) {
                    this.iframe.contentWindow.focus();
                }
            }
        } else replaceSelection(this.element, bbcode);
    }

    getSelection() {
        if (this.mode) {
            return this.window.getSelection();
        }
        return this.element.value.substring(
            this.element.selectionStart,
            this.element.selectionEnd,
        );
    }

    getSource() {
        return this.doc.body.innerHTML;
    }

    setSource(a) {
        if (this.doc && this.doc.body) this.doc.body.innerHTML = a;
    }

    switchMode(toggle) {
        const { element, iframe } = this;
        if (!toggle) {
            element.value = htmlToBBCode(this.getSource());
            element.style.display = '';
            iframe.style.display = 'none';
        } else {
            this.setSource(bbcodeToHTML(element.value));
            element.style.display = 'none';
            iframe.style.display = '';
        }
        this.mode = toggle;
    }

    submit() {
        if (this.mode) {
            this.switchMode(0);
            this.switchMode(1);
        }
    }
}

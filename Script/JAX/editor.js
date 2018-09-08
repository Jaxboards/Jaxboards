/* global globalsettings */
/* eslint-disable no-script-url, no-alert */

import Ajax from './ajax';
import Browser from './browser';
import {
  insertBefore,
  getComputedStyle,
} from './el';
import Event from './event';
import { bbcodeToHTML, htmlToBBCode } from './bbcode-utils';
import { assign } from './util';

const URL_REGEX = /^(ht|f)tps?:\/\/[\w.\-%&?=/]+$/;
const isURL = text => URL_REGEX.test(text);

class Editor {
  constructor(textarea, iframe) {
    if (!iframe.timedout) {
      iframe.timedout = true;
      setTimeout(() => {
        // eslint-disable-next-line no-new
        new Editor(textarea, iframe);
      }, 100);
      return null;
    }

    if (iframe.editor) {
      return null;
    }

    this.iframe = iframe;
    iframe.editor = this;
    iframe.className = 'editorframe';
    // 1 for html editing mode, 0 for textarea mode
    this.mode = Browser.mobile || Browser.n3ds ? 0 : globalsettings.wysiwyg;
    this.mode = this.mode || 0;
    this.textarea = textarea;
    this.window = iframe.contentWindow;
    this.doc = iframe.contentWindow.document;

    const cs = getComputedStyle(this.textarea);
    const body = this.doc.getElementsByTagName('body')[0];
    if (body && cs) {
      body.style.backgroundColor = cs.backgroundColor;
      body.style.color = cs.color;
      body.style.borderColor = '#FFF';
    }

    this.doc.designMode = 'on';

    this.editbar = document.createElement('div');
    this.buildEditBar();

    this.editbar.style.width = `${textarea.clientWidth + 2}px`;
    iframe.style.width = `${textarea.clientWidth}px`;
    iframe.style.height = `${textarea.clientHeight}px`;

    insertBefore(this.editbar, textarea);

    // Set the source and initialize the editor
    //
    this.setSource('<div></div>');
    setTimeout(() => {
      this.setSource(bbcodeToHTML(textarea.value));
      this.switchMode(this.mode);
    }, 100);
    return this;
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
      a.onclick = event => this.editbarCommand(event, cmd);
      this.editbar.appendChild(a);
    });
  }

  editbarCommand(event, cmd) {
    const e = Event(event).cancel();

    switch (cmd) {
      case 'forecolor':
      case 'backcolor':
        this.showColors(e.pageX, e.pageY, cmd);
        break;
      case 'c_smileys':
        this.showEmotes(e.pageX, e.pageY);
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
      this.createEmoteWindow.x = x;
      this.createEmoteWindow.y = y;
      new Ajax().load('/misc/emotes.php?json', this.createEmoteWindow);
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

  createEmoteWindow(xml) {
    const smilies = JSON.parse(xml.responseText);
    const emotewin = document.createElement('div');
    emotewin.className = 'emotewin';

    smilies.forEach((smiley, i) => {
      const r = document.createElement('a');
      r.href = 'javascript:void(0)';
      r.emotetext = smilies[0][i];
      r.onclick = () => {
        this.cmd('inserthtml', this.emotetext);
        this.hideEmotes();
      };
      r.innerHTML = `${smilies[1][i]} ${smilies[0][i]}`;
      emotewin.appendChild(r);
    });

    emotewin.style.position = 'absolute';
    emotewin.style.display = 'none';
    this.emoteWindow = emotewin;
    document.querySelector('#page').appendChild(emotewin);
    this.showEmotes(this.createEmoteWindow.x, this.createEmoteWindow.y);
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
    assign(colorwin.style, {
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
        assign(a.style, {
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
        if (!arg1.match(/^(https?|ftp|mailto):/)) arg1 = `https://${arg1}`;
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
    } else Editor.setSelection(this.textarea, bbcode);
  }

  getSelection() {
    if (this.mode) {
      return Browser.ie
        ? this.doc.selection.createRange().text
        : this.window.getSelection();
    }
    if (Browser.ie) {
      this.textarea.focus();
      return document.selection.createRange().text;
    }
    return this.textarea.value.substring(
      this.textarea.selectionStart,
      this.textarea.selectionEnd,
    );
  }

  getSource() {
    return this.doc.body.innerHTML;
  }

  setSource(a) {
    if (this.doc && this.doc.body) this.doc.body.innerHTML = a;
  }

  switchMode(toggle) {
    const t = this.textarea;
    const f = this.iframe;
    if (!toggle) {
      t.value = htmlToBBCode(this.getSource());
      t.style.display = '';
      f.style.display = 'none';
    } else {
      this.setSource(bbcodeToHTML(t.value));
      t.style.display = 'none';
      f.style.display = '';
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

Editor.setSelection = function setSelection(t, stuff) {
  const scroll = t.scrollTop;
  if (Browser.ie) {
    t.focus();
    document.selection.createRange().text = stuff;
  } else {
    const s = t.selectionStart;
    const e = t.selectionEnd;
    t.value = t.value.substring(0, s) + stuff + t.value.substr(e);
    t.selectionStart = s + stuff.length;
    t.selectionEnd = s + stuff.length;
  }
  t.focus();
  t.scrollTop = scroll;
};

export default Editor;

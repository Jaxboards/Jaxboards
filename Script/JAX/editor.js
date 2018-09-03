import Ajax from './ajax';
import Browser from './browser';
import Color from './color';
import {
  insertBefore,
  getComputedStyle,
} from './el';
import Event from './event';

class Editor {
  constructor(textarea, iframe) {
    if (!iframe.timedout) {
      iframe.timedout = true;
      setTimeout(() => {
        new Editor(textarea, iframe);
      }, 100);
      return;
    }
    if (iframe.editor) return;
    this.iframe = iframe;
    iframe.editor = this;
    iframe.className = 'editorframe';
    this.mode = Browser.mobile || Browser.n3ds ? 0 : globalsettings.wysiwyg; // 1 for html editing mode, 0 for textarea mode
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
    setTimeout(function () {
      this.setSource(this.BBtoHTML(textarea.value));
      this.switchMode(this.mode);
    }, 100);
    return me;
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

    const l = cmds.length;
    let a;
    let x;
    for (x = 0; x < l; x++) {
      a = document.createElement('a');
      a.className = cmds[x];
      a.title = cmddesc[x];
      a.href = 'javascript:void(0)';
      a.unselectable = 'on';
      a.onclick = event => this.editbarCommand(event, this.className);
      this.editbar.appendChild(a);
    }
  }

  editbarCommand(e, cmd) {
    e = Event(e).cancel();

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
    if (emotewin.style.display == 'none') {
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
    const rs = JSON.parse(xml.responseText);
    let x;
    let html;
    const emotewin = document.createElement('div');
    let r;
    let t;
    emotewin.className = 'emotewin';
    for (x = 0; x < rs[0].length; x++) {
      r = document.createElement('a');
      r.href = 'javascript:void(0)';
      r.emotetext = rs[0][x];
      r.onclick = () => {
        this.cmd('inserthtml', this.emotetext);
        this.hideEmotes();
      };
      r.innerHTML = `${rs[1][x]} ${rs[0][x]}`;
      emotewin.appendChild(r);
    }
    emotewin.style.position = 'absolute';
    emotewin.style.display = 'none';
    this.emoteWindow = emotewin;
    document.querySelector('#page').appendChild(emotewin);
    this.showEmotes(this.createEmoteWindow.x, this.createEmoteWindow.y);
  }

  colorHandler(cmd) {
    this.cmd(cmd, this.style.backgroundColor);
    this.hideColors();
  }

  showColors(posx, posy, cmd) {
    if (this.colorWindow && this.colorWindow.style.display != 'none') {
      return this.hideColors();
    }
    let colorwin = this.colorWindow;
    const colors = [
      'FFFFFF',
      'AAAAAA',
      '000000',
      'FF0000',
      '00FF00',
      '0000FF',
      'FFFF00',
      '00FFFF',
      'FF00FF',
    ];
    const l = colors.length;
    const sq = Math.ceil(Math.sqrt(l));
    let r;
    let c;
    let a;
    if (!colorwin) {
      colorwin = document.createElement('table');
      colorwin.style.borderCollapse = 'collapse';
      colorwin.style.position = 'absolute';
      for (y = 0; y < sq; y++) {
        r = colorwin.insertRow(y);
        for (x = 0; x < sq; x++) {
          c = r.insertCell(x);
          if (!colors[x + y * sq]) continue;
          c.style.border = '1px solid #000';
          c.style.padding = 0;
          a = document.createElement('a');
          a.href = 'javascript:void(0)';
          a.onclick = () => this.colorHandler(cmd);
          c.appendChild(a);
          c = a.style;
          c.display = 'block';
          c.backgroundColor = `#${colors[x + y * sq]}`;
          c.height = c.width = '20px';
          c.margin = 0;
        }
      }
      this.colorWindow = colorwin;
      document.querySelector('#page').appendChild(colorwin);
    } else {
      colorwin.style.display = '';
    }
    colorwin.style.top = `${posy}px`;
    colorwin.style.left = `${posx}px`;
  }

  hideColors() {
    if (this.colorWindow) {
      this.colorWindow.style.display = 'none';
    }
  }

  cmd(a, b, c) {
    a = a.toLowerCase();
    let rng;
    const selection = this.getSelection();
    let bbcode;
    switch (a) {
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
        b = prompt('Image URL:');
        if (!b) return;
        if (!b.match(/^(ht|f)tps?:\/\/[\w\.\-\%&\?=\/]+$/)) {
          return alert('Please enter a valid URL.');
        }
        bbcode = `[img]${b}[/img]`;
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
        b = prompt('Link:');
        if (!b) return;
        if (!b.match(/^(https?|ftp|mailto):/)) b = `https://${b}`;
        bbcode = `[url=${b}]${selection}[/url]`;
        break;
      case 'c_email':
        b = prompt('Email:');
        if (!b) return;
        a = 'createlink';
        b = `mailto:${b}`;
        bbcode = `[url=${b}]${selection}[/url]`;
        break;
      case 'backcolor':
        if (Browser.firefox || Browser.safari) a = 'hilitecolor';
        // a="inserthtml";b='<span style="background:'+b+'">'+selection+'</span>'
        bbcode = `[bgcolor=${b}]${selection}[/bgcolor]`;
        break;
      case 'forecolor':
        bbcode = `[color=${b}]${selection}[/color]`;
        break;
      case 'c_code':
        a = 'inserthtml';
        bbcode = b = `[code]${selection}[/code]`;
        break;
      case 'c_quote':
        a = 'inserthtml';
        b = prompt('Who said this?');
        b = bbcode = `[quote${b ? `=${b}` : ''}]${selection}[/quote]`;
        break;
      case 'c_spoiler':
        a = 'inserthtml';
        b = bbcode = `[spoiler]${selection}[/spoiler]`;
        break;
      case 'c_youtube':
        a = 'inserthtml';
        b = prompt('Video URL?');
        if (!b) return;
        b = bbcode = `[video]${b}[/video]`;
        break;
      case 'inserthtml':
        bbcode = b;
        break;
    }
    if (this.mode) {
      if (a == 'inserthtml' && Browser.ie) {
        rng = this.doc.selection.createRange();
        if (!rng.text.length) this.doc.body.innerHTML += b;
        else {
          rng.pasteHTML(b);
          rng.collapse(false);
          rng.select();
        }
      } else {
        this.doc.execCommand(a, false, b || false);
        if (this.iframe.contentWindow.focus) {
          this.iframe.contentWindow.focus();
        }
      }
    } else editor.setSelection(this.textarea, bbcode);
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

  BBtoHTML(a) {
    a = a
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/(\s) /g, '$1&nbsp;');
    a = this.replaceAll(a, /\[(b|i|u|s)\]([\w\W]*?)\[\/\1\]/gi, '<$1>$2</$1>');
    a = this.replaceAll(a, /\[img\]([^'"\[]+)\[\/img\]/gi, '<img src="$1">');
    a = this.replaceAll(
      a,
      /\[color=([^\]]+)\](.*?)\[\/color\]/gi,
      '<span style="color:$1">$2</span>',
    );
    a = this.replaceAll(
      a,
      /\[size=([^\]]+)\](.*?)\[\/size\]/gi,
      '<span style="font-size:$1">$2</span>',
    );
    a = this.replaceAll(
      a,
      /\[url=([^\]]+)\](.*?)\[\/url\]/gi,
      '<a href="$1">$2</a>',
    );
    a = this.replaceAll(
      a,
      /\[bgcolor=([^\]]+)\](.*?)\[\/bgcolor\]/gi,
      '<span style="backgroun-color:$1">$2</span>',
    );
    a = this.replaceAll(a, /\[h(\d)\](.*?)\[\/h\1\]/, '<h$1>$2</h$1>');
    a = this.replaceAll(
      a,
      /\[align=(left|right|center)\](.*?)\[\/align\]/,
      '<span style="text-align:$1">$2</span>',
    );
    a = this.replaceAll(a, /\[(ul|ol)\]([\w\W]*?)\[\/\1\]/gi, (s) => {
      const tag = RegExp.$1;
      let lis = '';
      const list = RegExp.$2.split(/([\r\n]+|^)\*/);
      let x;
      for (x = 0; x < list.length; x++) {
        if (list[x].match(/\S/)) lis += `<li>${list[x]}</li>`;
      }
      return `<${tag}>${lis}</${tag}>`;
    });
    a = this.replaceAll(a, /\n/g, '<br />');
    return a;
  }

  replaceAll(a, b, c) {
    let tmp = a;
    do {
      a = tmp;
      tmp = a.replace(b, c);
    } while (a != tmp);
    return tmp;
  }

  HTMLtoBB(a) {
    a = a.replace(/[\r\n]+/g, '');
    a = a.replace(/<(hr|br|meta)[^>]*>/gi, '\n');
    a = a.replace(/<img.*?src=["']?([^'"]+)["'][^>]*\/?>/g, '[img]$1[/img]');
    a = this.replaceAll(a, /<(\w+)([^>]*)>([\w\W]*?)<\/\1>/gi, (
      whole,
      tag,
      attributes,
      innerhtml,
    ) => {
      const att = {};
      let style = '';
      attributes.replace(
        /(color|size|style|href|src)=(['"]?)(.*?)\2/gi,
        (whole, attr, q, value) => {
          att[attr] = value;
        },
      );

      if (att.style) style = att.style;

      tag = tag.toLowerCase();
      if (tag == 'script' || tag == 'style' || tag == 'hr') return;
      if (style.match(/background(\-color)?:[^;]+(rgb\([^\)]+\)|#\s+)/i)) {
        innerhtml = `[bgcolor=#${
          new Color(RegExp.$2).toHex()
        }]${
          innerhtml
        }[/bgcolor]`;
      }
      if (style.match(/text\-align: ?(right|center|left);/i)) {
        innerhtml = `[align=${RegExp.$1}]${innerhtml}[/align]`;
      }
      if (
        style.match(/font\-style: ?italic;/i)
        || tag == 'i'
        || tag == 'em'
      ) {
        innerhtml = `[I]${innerhtml}[/I]`;
      }
      if (style.match(/text\-decoration:[^;]*underline;/i) || tag == 'u') {
        innerhtml = `[U]${innerhtml}[/U]`;
      }
      if (
        style.match(/text\-decoration:[^;]*line\-through;/i)
        || tag == 's'
      ) {
        innerhtml = `[S]${innerhtml}[/S]`;
      }
      if (
        style.match(/font\-weight: ?bold;/i)
        || tag == 'strong'
        || tag == 'b'
      ) {
        innerhtml = `[B]${innerhtml}[/B]`;
      }
      if (att.size || style.match(/font\-size: ?([^;]+)/i)) {
        innerhtml = `[size=${att.size || RegExp.$1}]${innerhtml}[/size]`;
      }
      if (att.color || style.match(/color: ?([^;]+)/i)) {
        innerhtml = `[color=${att.color || RegExp.$1}]${innerhtml}[/color]`;
      }
      if (tag == 'a' && att.href) {
        innerhtml = `[url=${att.href}]${innerhtml}[/url]`;
      }
      if (tag == 'ol') innerhtml = `[ol]${innerhtml}[/ol]`;
      if (tag == 'ul') innerhtml = `[ul]${innerhtml}[/ul]`;
      if (tag.match(/h\d/i)) {
        innerhtml = `[${
          tag.toLowerCase()
        }]${
          innerhtml
        }[/${
          tag.toLowerCase()
        }]`;
      }
      if (tag == 'li') {
        innerhtml = `*${innerhtml.replace(/[\n\r]+/, '')}\n`;
      }
      if (tag == 'p') {
        innerhtml = `\n${innerhtml == '&nbsp' ? '' : innerhtml}\n`;
      }
      if (tag == 'div') innerhtml = `\n${innerhtml}`;
      return innerhtml;
    });
    return a
      .replace(/&gt;/g, '>')
      .replace(/&amp;/g, '&')
      .replace(/&lt;/g, '<')
      .replace(/&nbsp;/g, ' ');
  }

  switchMode(toggle) {
    const t = this.textarea;
    const f = this.iframe;
    if (!toggle) {
      t.value = this.HTMLtoBB(this.getSource());
      t.style.display = '';
      f.style.display = 'none';
    } else {
      this.setSource(this.BBtoHTML(t.value));
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

Editor.setSelection = function (t, stuff) {
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

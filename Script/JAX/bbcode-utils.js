import Color from './color';

const DISALLOWED_TAGS = [
  'SCRIPT',
  'STYLE',
  'HR',
];

export function htmlToBBCode(html) {
  let bbcode = html;
  bbcode = bbcode.replace(/[\r\n]+/g, '');
  bbcode = bbcode.replace(/<(hr|br|meta)[^>]*>/gi, '\n');
  bbcode = bbcode.replace(/<img.*?src=["']?([^'"]+)["'][^>]*\/?>/g, '[img]$1[/img]');
  bbcode = bbcode.replace(/<(\w+)([^>]*)>([\w\W]*?)<\/\1>/gi, (
    whole,
    tag,
    attributes,
    innerHTML,
  ) => {
    let innerhtml = innerHTML;
    const att = {};
    attributes.replace(
      /(color|size|style|href|src)=(['"]?)(.*?)\2/gi,
      (_, attr, q, value) => {
        att[attr] = value;
      },
    );
    const { style = '' } = att;

    const lcTag = tag.toLowerCase();
    if (DISALLOWED_TAGS.includes(lcTag)) {
      return '';
    }
    if (style.match(/background(-color)?:[^;]+(rgb\([^)]+\)|#\s+)/i)) {
      innerhtml = `[bgcolor=#${
        new Color(RegExp.$2).toHex()
      }]${
        innerhtml
      }[/bgcolor]`;
    }
    if (style.match(/text-align: ?(right|center|left);/i)) {
      innerhtml = `[align=${RegExp.$1}]${innerhtml}[/align]`;
    }
    if (
      style.match(/font-style: ?italic;/i)
      || lcTag === 'i'
      || lcTag === 'em'
    ) {
      innerhtml = `[I]${innerhtml}[/I]`;
    }
    if (style.match(/text-decoration:[^;]*underline;/i) || lcTag === 'u') {
      innerhtml = `[U]${innerhtml}[/U]`;
    }
    if (
      style.match(/text-decoration:[^;]*line-through;/i)
      || lcTag === 's'
    ) {
      innerhtml = `[S]${innerhtml}[/S]`;
    }
    if (
      style.match(/font-weight: ?bold;/i)
      || lcTag === 'strong'
      || lcTag === 'b'
    ) {
      innerhtml = `[B]${innerhtml}[/B]`;
    }
    if (att.size || style.match(/font-size: ?([^;]+)/i)) {
      innerhtml = `[size=${att.size || RegExp.$1}]${innerhtml}[/size]`;
    }
    if (att.color || style.match(/color: ?([^;]+)/i)) {
      innerhtml = `[color=${att.color || RegExp.$1}]${innerhtml}[/color]`;
    }
    if (lcTag === 'a' && att.href) {
      innerhtml = `[url=${att.href}]${innerhtml}[/url]`;
    }
    if (lcTag === 'ol') innerhtml = `[ol]${innerhtml}[/ol]`;
    if (lcTag === 'ul') innerhtml = `[ul]${innerhtml}[/ul]`;
    if (lcTag.match(/h\d/i)) {
      innerhtml = `[${
        lcTag
      }]${
        innerhtml
      }[/${
        lcTag
      }]`;
    }
    if (lcTag === 'li') {
      innerhtml = `*${innerhtml.replace(/[\n\r]+/, '')}\n`;
    }
    if (lcTag === 'p') {
      innerhtml = `\n${innerhtml === '&nbsp' ? '' : innerhtml}\n`;
    }
    if (lcTag === 'div') {
      innerhtml = `\n${innerhtml}`;
    }
    return innerhtml;
  });
  return bbcode
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&nbsp;/g, ' ');
}


export function bbcodeToHTML(bbcode) {
  let html = bbcode
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/(\s) /g, '$1&nbsp;');
  html = html.replace(/\[(b|i|u|s)\]([\w\W]*?)\[\/\1\]/gi, '<$1>$2</$1>');
  html = html.replace(/\[img\]([^'"[]+)\[\/img\]/gi, '<img src="$1">');
  html = html.replace(
    /\[color=([^\]]+)\](.*?)\[\/color\]/gi,
    '<span style="color:$1">$2</span>',
  );
  html = html.replace(
    /\[size=([^\]]+)\](.*?)\[\/size\]/gi,
    '<span style="font-size:$1">$2</span>',
  );
  html = html.replace(
    /\[url=([^\]]+)\](.*?)\[\/url\]/gi,
    '<a href="$1">$2</a>',
  );
  html = html.replace(
    /\[bgcolor=([^\]]+)\](.*?)\[\/bgcolor\]/gi,
    '<span style="backgroun-color:$1">$2</span>',
  );
  html = html.replace(/\[h(\d)\](.*?)\[\/h\1\]/g, '<h$1>$2</h$1>');
  html = html.replace(
    /\[align=(left|right|center)\](.*?)\[\/align\]/g,
    '<span style="text-align:$1">$2</span>',
  );
  html = html.replace(/\[(ul|ol)\]([\w\W]*?)\[\/\1\]/gi, (match) => {
    const tag = match[1];
    const listItems = match[2].split(/([\r\n]+|^)\*/);
    const lis = listItems
      .filter(text => text.trim())
      .map(text => `<li>${text}</li>`)
      .join('');
    return `<${tag}>${lis}</${tag}>`;
  });
  html = html.replace(/\n/g, '<br />');
  return html;
}
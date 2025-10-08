import Color from './color';

const DISALLOWED_TAGS = ['SCRIPT', 'STYLE', 'HR'];

const textAlignRegex = /text-align: ?(right|center|left)/i;
const backgroundColorRegex = /background(-color)?:[^;]+(rgb\([^)]+\)|#\s+)/i;
const italicRegex = /font-style: ?italic/i;
const underlineRegex = /text-decoration:[^;]*underline/i;
const lineThroughRegex = /text-decoration:[^;]*line-through/i;
const fontSizeRegex = /font-size: ?([^;]+)/i;
const fontColorRegex = /color: ?([^;]+)/i;
const fontWeightRegex = /font-weight: ?bold/i;

export function htmlToBBCode(html: string) {
    let bbcode = html;
    const nestedTagRegex = /<(\w+)([^>]*)>([^]*?)<\/\1>/i;
    bbcode = bbcode.replaceAll(/[\r\n]+/, '');
    bbcode = bbcode.replaceAll(/<(hr|br|meta)[^>]*>/i, '\n');
    // images and emojis
    bbcode = bbcode.replaceAll(
        /<img.*?src=["']?([^'"]+)["'](?: alt=["']?([^"']+)["'])?[^>]*\/?>/,
        (_: string, src: string, alt: string) => alt || `[img]${src}[/img]`,
    );
    bbcode = bbcode.replaceAll(
        nestedTagRegex,
        (_: string, tag: string, attributes: string, innerHTML: string) => {
            // Recursively handle nested tags
            let innerhtml = nestedTagRegex.test(innerHTML)
                ? htmlToBBCode(innerHTML)
                : innerHTML;
            const att: Record<string, string> = {};
            attributes.replaceAll(
                /(color|size|style|href|src)=(['"]?)(.*?)\2/i,
                (__: string, attr: string, q: string, value: string) => {
                    att[attr] = value;
                    return '';
                },
            );
            const { style = '' } = att;

            const lcTag = tag.toLowerCase();
            if (DISALLOWED_TAGS.includes(lcTag)) {
                return '';
            }

            const textAlignMatch = textAlignRegex.exec(style);
            const backgroundColorMatch = backgroundColorRegex.exec(style);
            const italicMatch = italicRegex.exec(style);
            const underlineMatch = underlineRegex.exec(style);
            const lineThroughMatch = lineThroughRegex.exec(style);

            const fontSizeMatch = fontSizeRegex.exec(style);
            const fontColorMatch = fontColorRegex.exec(style);
            const fontWeightMatch = fontWeightRegex.exec(style);

            if (backgroundColorMatch) {
                innerhtml = `[bgcolor=#${new Color(
                    backgroundColorMatch[2],
                ).toHex()}]${innerhtml}[/bgcolor]`;
            }
            if (textAlignMatch) {
                innerhtml = `[align=${textAlignMatch[1]}]${innerhtml}[/align]`;
            }

            if (italicMatch || lcTag === 'i' || lcTag === 'em') {
                innerhtml = `[I]${innerhtml}[/I]`;
            }

            if (underlineMatch || lcTag === 'u') {
                innerhtml = `[U]${innerhtml}[/U]`;
            }

            if (lineThroughMatch || lcTag === 's' || lcTag === 'strike') {
                innerhtml = `[S]${innerhtml}[/S]`;
            }

            if (fontWeightMatch || lcTag === 'strong' || lcTag === 'b') {
                innerhtml = `[B]${innerhtml}[/B]`;
            }

            if (att.size || fontSizeMatch) {
                innerhtml = `[size=${att.size || fontSizeMatch?.[1]}]${innerhtml}[/size]`;
            }

            if (att.color || fontColorMatch) {
                innerhtml = `[color=${att.color || fontColorMatch?.[1]}]${innerhtml}[/color]`;
            }

            if (lcTag === 'a' && att.href) {
                innerhtml = `[url=${att.href}]${innerhtml}[/url]`;
            }

            if (lcTag === 'ol') innerhtml = `[ol]${innerhtml}[/ol]`;
            if (lcTag === 'ul') innerhtml = `[ul]${innerhtml}[/ul]`;

            // h1-h6
            if (/h\d/i.test(lcTag)) {
                innerhtml = `[${lcTag}]${innerhtml}[/${lcTag}]`;
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
        },
    );
    return bbcode
        .replaceAll(/&amp;/, '&')
        .replaceAll(/&gt;/, '>')
        .replaceAll(/&lt;/, '<')
        .replaceAll(/&nbsp;/, ' ');
}

export function bbcodeToHTML(bbcode: string) {
    let html = bbcode
        .replaceAll(/</, '&lt;')
        .replaceAll(/>/, '&gt;')
        .replaceAll(/(\s) /, '$1&nbsp;');
    html = html.replaceAll(/\[b\]([^]*?)\[\/b\]/i, '<b>$1</b>');
    html = html.replaceAll(/\[i\]([^]*?)\[\/i\]/i, '<i>$1</i>');
    html = html.replaceAll(/\[u\]([^]*?)\[\/u\]/i, '<u>$1</u>');
    html = html.replaceAll(/\[s\]([^]*?)\[\/s\]/i, '<s>$1</s>');
    html = html.replaceAll(/\[img\]([^'"[]+)\[\/img\]/i, '<img src="$1">');
    html = html.replaceAll(
        /\[color=([^\]]+)\](.*?)\[\/color\]/i,
        '<span style="color:$1">$2</span>',
    );
    html = html.replaceAll(
        /\[size=([^\]]+)\](.*?)\[\/size\]/i,
        '<span style="font-size:$1">$2</span>',
    );
    html = html.replaceAll(
        /\[url=([^\]]+)\](.*?)\[\/url\]/i,
        '<a href="$1">$2</a>',
    );
    html = html.replaceAll(
        /\[bgcolor=([^\]]+)\](.*?)\[\/bgcolor\]/i,
        '<span style="backgroun-color:$1">$2</span>',
    );
    html = html.replaceAll(/\[h(\d)\](.*?)\[\/h\1\]/, '<h$1>$2</h$1>');
    html = html.replaceAll(
        /\[align=(left|right|center)\](.*?)\[\/align\]/,
        '<div style="text-align:$1">$2</div>',
    );
    html = html.replaceAll(
        /\[(ul|ol)\]([^]*?)\[\/\1\]/i,
        (_, tag, contents) => {
            const listItems = contents.split(/(^|[\r\n]+)\*/);
            const lis = listItems
                .filter((text: string) => text.trim())
                .map((text: string) => `<li>${text}</li>`)
                .join('');
            return `<${tag}>${lis}</${tag}>`;
        },
    );
    html = html.replaceAll(/\n/, '<br />');
    return html;
}

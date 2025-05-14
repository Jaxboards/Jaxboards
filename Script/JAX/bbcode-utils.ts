import Color from './color';

const DISALLOWED_TAGS = ['SCRIPT', 'STYLE', 'HR'];

export function htmlToBBCode(html: string) {
    let bbcode = html;
    const nestedTagRegex = /<(\w+)([^>]*)>([^]*?)<\/\1>/gi;
    bbcode = bbcode.replace(/[\r\n]+/g, '');
    bbcode = bbcode.replace(/<(hr|br|meta)[^>]*>/gi, '\n');
    // images and emojis
    bbcode = bbcode.replace(
        /<img.*?src=["']?([^'"]+)["'](?: alt=["']?([^"']+)["'])?[^>]*\/?>/g,
        (_: string, src: string, alt: string) => alt || `[img]${src}[/img]`,
    );
    bbcode = bbcode.replace(
        nestedTagRegex,
        (_: string, tag: string, attributes: string, innerHTML: string) => {
            // Recursively handle nested tags
            let innerhtml = nestedTagRegex.test(innerHTML)
                ? htmlToBBCode(innerHTML)
                : innerHTML;
            const att: Record<string,string> = {};
            attributes.replace(
                /(color|size|style|href|src)=(['"]?)(.*?)\2/gi,
                (_: string, attr: string, q: string, value: string) => {
                    att[attr] = value;
                    return '';
                },
            );
            const { style = '' } = att;

            const lcTag = tag.toLowerCase();
            if (DISALLOWED_TAGS.includes(lcTag)) {
                return '';
            }

            const textAlignMatch = style.match(
                /text-align: ?(right|center|left)/i,
            );
            const backgroundColorMatch = style.match(
                /background(-color)?:[^;]+(rgb\([^)]+\)|#\s+)/i,
            );
            const italicMatch = style.match(/font-style: ?italic/i);
            const underlineMatch = style.match(
                /text-decoration:[^;]*underline/i,
            );
            const lineThroughMatch = style.match(
                /text-decoration:[^;]*line-through/i,
            );
            const fontSizeMatch = style.match(/font-size: ?([^;]+)/i);
            const fontColorMatch = style.match(/color: ?([^;]+)/i);
            const fontWeightMatch = style.match(/font-weight: ?bold/i);

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
            if (lcTag.match(/h\d/i)) {
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
        .replace(/&amp;/g, '&')
        .replace(/&gt;/g, '>')
        .replace(/&lt;/g, '<')
        .replace(/&nbsp;/g, ' ');
}

export function bbcodeToHTML(bbcode: string) {
    let html = bbcode
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/(\s) /g, '$1&nbsp;');
    html = html.replace(/\[b\]([^]*?)\[\/b\]/gi, '<b>$1</b>');
    html = html.replace(/\[i\]([^]*?)\[\/i\]/gi, '<i>$1</i>');
    html = html.replace(/\[u\]([^]*?)\[\/u\]/gi, '<u>$1</u>');
    html = html.replace(/\[s\]([^]*?)\[\/s\]/gi, '<s>$1</s>');
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
        '<div style="text-align:$1">$2</div>',
    );
    html = html.replace(/\[(ul|ol)\]([^]*?)\[\/\1\]/gi, (_, tag, contents) => {
        const listItems = contents.split(/(^|[\r\n]+)\*/);
        const lis = listItems
            .filter((text: string) => text.trim())
            .map((text: string) => `<li>${text}</li>`)
            .join('');
        return `<${tag}>${lis}</${tag}>`;
    });
    html = html.replace(/\n/g, '<br />');
    return html;
}

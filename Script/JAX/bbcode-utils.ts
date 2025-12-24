import Color from './color';

const DISALLOWED_TAGS = new Set(['script', 'style', 'hr']);

const tagsToBBCode: Record<
    string,
    (inner: string, attrs?: Record<string, string>) => string
> = {
    a: (inner, attrs) => `[url=${attrs?.href}]${inner}[/url]`,

    b(...args) {
        return this.strong(...args);
    },

    div: (inner) => `\n${inner}`,

    em: (inner) => `[i]${inner}[/i]`,

    h1: (inner) => `[h1]${inner}[/h1]`,
    h2: (inner) => `[h2]${inner}[/h2]`,
    h3: (inner) => `[h3]${inner}[/h3]`,
    h4: (inner) => `[h4]${inner}[/h4]`,
    h5: (inner) => `[h5]${inner}[/h5]`,
    h6: (inner) => `[h6]${inner}[/h6]`,

    i(...args) {
        return this.em(...args);
    },

    li: (inner) => `*${inner.replace(/[\n\r]+/, '')}\n`,

    ol: (inner) => `[ol]${inner}[/ol]`,

    p: (inner) => `\n${inner === '&nbsp' ? '' : inner}\n`,

    strike: (inner) => `[s]${inner}[/s]`,

    strong: (inner) => `[b]${inner}[/b]`,

    u: (inner) => `[u]${inner}[/u]`,

    ul: (inner) => `[ul]${inner}[/ul]`,
};

const styleToBBCode: Record<
    string,
    (attrValue: string, inner: string) => string
> = {
    'background-color': (bgColor, inner) =>
        `[bgcolor=${bgColor}]${inner}[/bgcolor]`,

    color(color, inner) {
        const hex = color && new Color(color).toHex();
        const colorAttribute = hex ? `${hex}` : color;

        return `[color=${colorAttribute}]${inner}[/color]`;
    },

    'font-style': (fontStyle, inner) => `[i]${inner}[/i]`,

    'font-size': (size, inner) => `[size=${size}]${inner}[/size]`,

    'font-weight': (weight, inner) => tagsToBBCode.strong(inner),

    'text-align': (align, inner) => `[align=${align}]${inner}[/align]`,

    'text-decoration': function textDecoration(value, inner) {
        return value === 'line-through'
            ? `[strike]${inner}[/strike]`
            : `[u]${inner}[/u]`;
    },
};

function parseTagToBBCode(
    tag: string,
    _inner: string,
    attrs: Record<string, string>,
) {
    let inner = _inner;

    const lcTag = tag.toLowerCase();
    if (DISALLOWED_TAGS.has(lcTag)) {
        return '';
    }

    if (lcTag in tagsToBBCode) {
        inner = tagsToBBCode[lcTag](inner, attrs);
    }

    return inner;
}

function parseStyleToBBCode(style: string, _inner: string): string {
    let inner = _inner;

    const dummyEl = document.createElement('div');
    dummyEl.setAttribute('style', style);

    for (const prop of dummyEl.style) {
        if (prop in styleToBBCode) {
            inner = styleToBBCode[prop](
                dummyEl.style.getPropertyValue(prop),
                inner,
            );
        }
    }

    return inner;
}

// TODO: this function should not use RegEx to parse HTML
export function htmlToBBCode(html: string) {
    let bbcode = html;
    const nestedTagRegex = /<(\w+)([^>]*)>([^]*?)<\/\1>/gi;
    bbcode = bbcode.replaceAll(/[\r\n]+/g, '');
    bbcode = bbcode.replaceAll(/<(hr|br|meta)[^>]*>/gi, '\n');
    // images and emojis
    bbcode = bbcode.replaceAll(
        /<img.*?src=["']?([^'"]+)["'](?: alt=["']?([^"']+)["'])?[^>]*\/?>/g,
        (_: string, src: string, alt: string) => alt || `[img]${src}[/img]`,
    );
    bbcode = bbcode.replaceAll(
        nestedTagRegex,
        (_: string, tag: string, attributes: string, innerHTML: string) => {
            // Recursively handle nested tags
            let innerhtml = nestedTagRegex.test(innerHTML)
                ? htmlToBBCode(innerHTML)
                : innerHTML;
            const attrs: Record<string, string> = {};
            attributes.replaceAll(
                /(color|size|style|href|src)=(['"]?)(.*?)\2/gi,
                (__: string, attr: string, q: string, value: string) => {
                    attrs[attr] = value;
                    return '';
                },
            );

            if (attrs.style) {
                innerhtml = parseStyleToBBCode(attrs.style, innerhtml);
            }

            if (attrs.size) {
                innerhtml = styleToBBCode.fontSize(attrs.size, innerhtml);
            }

            if (attrs.color) {
                innerhtml = styleToBBCode.color(attrs.color, innerhtml);
            }

            return parseTagToBBCode(tag, innerhtml, attrs);
        },
    );

    return bbcode
        .replaceAll('&amp;', '&')
        .replaceAll('&gt;', '>')
        .replaceAll('&lt;', '<')
        .replaceAll('&nbsp;', ' ');
}

export function bbcodeToHTML(bbcode: string) {
    let html = bbcode
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll(/(\s) /g, '$1&nbsp;');
    html = html.replaceAll(/\[b\]([^]*?)\[\/b\]/gi, '<b>$1</b>');
    html = html.replaceAll(/\[i\]([^]*?)\[\/i\]/gi, '<i>$1</i>');
    html = html.replaceAll(/\[u\]([^]*?)\[\/u\]/gi, '<u>$1</u>');
    html = html.replaceAll(/\[s\]([^]*?)\[\/s\]/gi, '<s>$1</s>');
    html = html.replaceAll(/\[img\]([^'"[]+)\[\/img\]/gi, '<img src="$1">');
    html = html.replaceAll(
        /\[color=([^\]]+)\](.*?)\[\/color\]/gi,
        '<span style="color:$1">$2</span>',
    );
    html = html.replaceAll(
        /\[size=([^\]]+)\](.*?)\[\/size\]/gi,
        '<span style="font-size:$1">$2</span>',
    );
    html = html.replaceAll(
        /\[url=([^\]]+)\](.*?)\[\/url\]/gi,
        '<a href="$1">$2</a>',
    );
    html = html.replaceAll(
        /\[bgcolor=([^\]]+)\](.*?)\[\/bgcolor\]/gi,
        '<span style="background-color:$1">$2</span>',
    );
    html = html.replaceAll(/\[h(\d)\](.*?)\[\/h\1\]/g, '<h$1>$2</h$1>');
    html = html.replaceAll(
        /\[align=(left|right|center)\](.*?)\[\/align\]/g,
        '<div style="text-align:$1">$2</div>',
    );
    html = html.replaceAll(
        /\[(ul|ol)\]([^]*?)\[\/\1\]/gi,
        (_, tag, contents) => {
            const listItems = contents.split(/(^|[\r\n]+)\*/);
            const lis = listItems
                .filter((text: string) => text.trim())
                .map((text: string) => `<li>${text}</li>`)
                .join('');
            return `<${tag}>${lis}</${tag}>`;
        },
    );
    html = html.replaceAll('\n', '<br />');
    return html;
}

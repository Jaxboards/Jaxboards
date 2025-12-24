import Color from './color';

const tagsToBBCode: Record<string, (inner: string, el: HTMLElement) => string> =
    {
        a: (inner, el) => `[url=${el?.getAttribute('href')}]${inner}[/url]`,

        b(...args) {
            return this.strong(...args);
        },

        br: () => '\n',

        div: (inner) => `\n${inner}`,

        em: (inner) => `[i]${inner}[/i]`,

        h1: (inner) => `[h1]${inner}[/h1]`,
        h2: (inner) => `[h2]${inner}[/h2]`,
        h3: (inner) => `[h3]${inner}[/h3]`,
        h4: (inner) => `[h4]${inner}[/h4]`,
        h5: (inner) => `[h5]${inner}[/h5]`,
        h6: (inner) => `[h6]${inner}[/h6]`,

        hr: () => '\n',

        i(...args) {
            return this.em(...args);
        },

        img(inner, img) {
            const alt = img.getAttribute('alt') ?? '';
            const src = img.getAttribute('src');
            return src ? `[img]${src}[/img]` : alt;
        },

        li: (inner) => `*${inner.replace(/[\n\r]+/, '')}\n`,

        meta: () => '\n',

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

    'font-weight': (weight, inner) => `[b]${inner}[/b]`,

    'text-align': (align, inner) => `[align=${align}]${inner}[/align]`,

    'text-decoration': function textDecoration(value, inner) {
        return value === 'line-through'
            ? `[strike]${inner}[/strike]`
            : `[u]${inner}[/u]`;
    },
};

function parseStyleToBBCode(
    style: CSSStyleDeclaration,
    _inner: string,
): string {
    let inner = _inner;

    for (const prop of style) {
        if (prop in styleToBBCode) {
            inner = styleToBBCode[prop](style.getPropertyValue(prop), inner);
        }
    }

    return inner;
}

function parseTextToBBCode(text: string) {
    return text
        .replaceAll(/[\r\n]+/g, '')
        .replaceAll('&amp;', '&')
        .replaceAll('&gt;', '>')
        .replaceAll('&lt;', '<')
        .replaceAll('&nbsp;', ' ');
}

export function htmlToBBCode(html: HTMLElement): string {
    const tagName = html.tagName.toLowerCase();

    let inner = html.childNodes.length
        ? Array.from(html.childNodes)
              .map((child) =>
                  child.nodeType === Node.ELEMENT_NODE
                      ? htmlToBBCode(child as HTMLElement)
                      : parseTextToBBCode(child.textContent ?? ''),
              )
              .join('')
        : '';

    // Don't inherit body styles
    if (tagName === 'body') {
        return inner;
    }

    if (html.style) {
        inner = parseStyleToBBCode(html.style, inner);
    }

    const sizeAttribute = html.getAttribute('size');
    if (sizeAttribute) {
        inner = styleToBBCode.fontSize(sizeAttribute, inner);
    }

    const colorAttribute = html.getAttribute('color');
    if (colorAttribute) {
        inner = styleToBBCode.color(colorAttribute, inner);
    }

    if (tagName in tagsToBBCode) {
        inner = tagsToBBCode[tagName](inner, html);
    }

    return inner;
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
        (_, tag: string, contents: string) => {
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

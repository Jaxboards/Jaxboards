import Color from "./color";

const tagsToBBCode: Record<string, (inner: string, el: HTMLElement) => string> =
  {
    a: (inner, el) => `[url=${el?.getAttribute("href")}]${inner}[/url]`,

    b(...args) {
      return this.strong(...args);
    },

    br: () => "\n",

    div: (inner) => `\n${inner}`,

    em: (inner) => `[i]${inner}[/i]`,

    font: (inner, el) =>
      `[font=${el.style.fontFamily || el.getAttribute("face")}]${inner}[/font]`,

    h1: (inner) => `[h1]${inner}[/h1]`,
    h2: (inner) => `[h2]${inner}[/h2]`,
    h3: (inner) => `[h3]${inner}[/h3]`,
    h4: (inner) => `[h4]${inner}[/h4]`,
    h5: (inner) => `[h5]${inner}[/h5]`,
    h6: (inner) => `[h6]${inner}[/h6]`,

    hr: () => "\n",

    i(...args) {
      return this.em(...args);
    },

    img: (inner, img) => {
      const alt = img.getAttribute("alt") ?? "";
      const src = img.getAttribute("src");
      if (img.dataset.emoji) {
        return ` ${img.dataset.emoji}`;
      }
      if (src) {
        return `[img]${src}[/img]`;
      }
      return alt;
    },

    li: (inner) => `*${inner.replace(/[\n\r]+/, "")}\n`,

    meta: () => "\n",

    ol: (inner) => `[ol]${inner}[/ol]`,

    p: (inner) => `\n${inner === "&nbsp" ? "" : inner}\n`,

    strike: (inner) => `[s]${inner}[/s]`,

    strong: (inner) => `[b]${inner}[/b]`,

    u: (inner) => `[u]${inner}[/u]`,

    ul: (inner) => `[ul]${inner}[/ul]`,
  };

const styleToBBCode: Record<
  string,
  (attrValue: string, inner: string) => string
> = {
  "background-color": (bgColor, inner) => {
    const hex = bgColor && new Color(bgColor).toHex();
    const colorAttribute = hex ? `${hex}` : bgColor;
    // ignore white/transparent bg
    if (["ffffff", "white", "transparent"].includes(colorAttribute)) {
      return inner;
    }
    return `[bgcolor=${colorAttribute}]${inner}[/bgcolor]`;
  },

  color(color, inner) {
    const hex = color && new Color(color).toHex();
    const colorAttribute = hex ? `${hex}` : color;

    return `[color=${colorAttribute}]${inner}[/color]`;
  },

  "font-family": (fontFace, inner) =>
    `[font=${fontFace.replaceAll(/['"]/g, "")}]${inner}[/font]`,

  "font-style": (fontStyle, inner) => `[i]${inner}[/i]`,

  "font-size": (size, inner) => `[size=${size}]${inner}[/size]`,

  "font-weight": (weight, inner) => `[b]${inner}[/b]`,

  "text-align": (align, inner) => `[align=${align}]${inner}[/align]`,

  "text-decoration": function textDecoration(value, inner) {
    return value === "line-through"
      ? `[strike]${inner}[/strike]`
      : `[u]${inner}[/u]`;
  },
};

function unescapeEntities(text: string) {
  return text
    .replaceAll(/[\r\n]+/g, "")
    .replaceAll("&amp;", "&")
    .replaceAll("&gt;", ">")
    .replaceAll("&lt;", "<")
    .replaceAll("&nbsp;", " ");
}

export function htmlToBBCode(html: HTMLElement): string {
  const tagName = html.tagName.toLowerCase();
  let inner = "";

  // Depth-first traversal
  for (const childNode of html.childNodes) {
    inner +=
      childNode.nodeType === Node.ELEMENT_NODE
        ? htmlToBBCode(childNode as HTMLElement)
        : unescapeEntities(childNode.textContent ?? "");
  }

  // Don't inherit body styles
  if (tagName === "body") {
    return inner;
  }

  // Inline styles
  if (html.style) {
    for (const prop of html.style) {
      if (prop in styleToBBCode) {
        inner = styleToBBCode[prop](html.style.getPropertyValue(prop), inner);
      }
    }
  }

  // Legacy size attribute
  const sizeAttribute = html.getAttribute("size");
  if (sizeAttribute) {
    inner = styleToBBCode.fontSize(sizeAttribute, inner);
  }

  // Legacy color attribute
  const colorAttribute = html.getAttribute("color");
  if (colorAttribute) {
    inner = styleToBBCode.color(colorAttribute, inner);
  }

  // Infer bbcode from tagName
  if (tagName in tagsToBBCode) {
    inner = tagsToBBCode[tagName](inner, html);
  }

  return inner;
}

export function bbcodeToHTML(bbcode: string) {
  return bbcode
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll(/(\s) /g, "$1&nbsp;")

    .replaceAll(/\[b\]([^]*?)\[\/b\]/gi, "<b>$1</b>")
    .replaceAll(/\[i\]([^]*?)\[\/i\]/gi, "<i>$1</i>")
    .replaceAll(/\[u\]([^]*?)\[\/u\]/gi, "<u>$1</u>")
    .replaceAll(/\[s\]([^]*?)\[\/s\]/gi, "<s>$1</s>")
    .replaceAll(/\[img\]([^'"[]+)\[\/img\]/gi, '<img src="$1">')
    .replaceAll(
      /\[color=([^\]]+)\]([^]*?)\[\/color\]/gi,
      '<span style="color:$1">$2</span>',
    )
    .replaceAll(
      /\[size=([^\]]+)\]([^]*?)\[\/size\]/gi,
      '<span style="font-size:$1">$2</span>',
    )
    .replaceAll(
      /\[font=['"]?([^\]]+?)['"]?\]([^]*?)\[\/font\]/gi,
      '<span style="font-family:$1">$2</span>',
    )
    .replaceAll(/\[url=([^\]]+)\]([^]*?)\[\/url\]/gi, '<a href="$1">$2</a>')
    .replaceAll(
      /\[bgcolor=([^\]]+)\]([^]*?)\[\/bgcolor\]/gi,
      '<span style="background-color:$1">$2</span>',
    )
    .replaceAll(/\[h(\d)\]([^]*?)\[\/h\1\]/g, "<h$1>$2</h$1>")
    .replaceAll(
      /\[align=(left|right|center)\]([^]*?)\[\/align\]/g,
      '<div style="text-align:$1">$2</div>',
    )
    .replaceAll(
      /\[(ul|ol)\]([^]*?)\[\/\1\]/gi,
      (_, tag: string, contents: string) => {
        const listItems = contents.split(/(^|[\r\n]+)\*/);
        const lis = listItems
          .filter((text: string) => text.trim())
          .map((text: string) => `<li>${text}</li>`)
          .join("");
        return `<${tag}>${lis}</${tag}>`;
      },
    )
    .replaceAll("\n", "<br>");
}

import { bbcodeToHTML } from './bbcode-utils';

test('bbcodeToHTML', function () {
    expect(bbcodeToHTML('[b]Bold Text[/b]')).toBe('<b>Bold Text</b>');
    expect(bbcodeToHTML('[i]Italic Text[/i]')).toBe('<i>Italic Text</i>');
    expect(bbcodeToHTML('[u]Underlined Text[/u]')).toBe(
        '<u>Underlined Text</u>',
    );
    expect(bbcodeToHTML('[s]Strikethrough Text[/s]')).toBe(
        '<s>Strikethrough Text</s>',
    );
    expect(bbcodeToHTML('[img]http://example.com/image.jpg[/img]')).toBe(
        '<img src="http://example.com/image.jpg">',
    );
    expect(bbcodeToHTML('[color=red]Red Text[/color]')).toBe(
        '<span style="color:red">Red Text</span>',
    );
    expect(bbcodeToHTML('[size=20px]Large Text[/size]')).toBe(
        '<span style="font-size:20px">Large Text</span>',
    );
    expect(bbcodeToHTML("[font='Arial']Arial Text[/font]")).toBe(
        '<span style="font-family:Arial">Arial Text</span>',
    );
    expect(bbcodeToHTML('[url=http://example.com]Example Link[/url]')).toBe(
        '<a href="http://example.com">Example Link</a>',
    );
});

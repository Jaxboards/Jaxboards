class Color {
    constructor(colorToParse) {
        let a = colorToParse;
        // RGB
        if (typeof a === 'object') this.rgb = a;
        else if (typeof a === 'string') {
            const rgbMatch = a.match(/^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i);
            const hexMatch = a.match(/#?[^\da-fA-F]/);
            if (rgbMatch) {
                rgbMatch[1] = parseFloat(rgbMatch[1]);
                rgbMatch[2] = parseFloat(rgbMatch[2]);
                rgbMatch[3] = parseFloat(rgbMatch[3]);
                rgbMatch.shift();
                this.rgb = rgbMatch;
            } else if (hexMatch) {
                if (a.charAt(0) === '#') {
                    a = a.substr(1);
                }
                if (a.length === 3) {
                    a =
                        a.charAt(0) +
                        a.charAt(0) +
                        a.charAt(1) +
                        a.charAt(1) +
                        a.charAt(2) +
                        a.charAt(2);
                }
                if (a.length !== 6) this.rgb = [0, 0, 0];
                else {
                    this.rgb = [];
                    for (let x = 0; x < 3; x += 1) {
                        this.rgb[x] = parseInt(a.substr(x * 2, 2), 16);
                    }
                }
            }
        } else {
            this.rgb = [0, 0, 0];
        }
    }

    invert() {
        this.rgb = [255 - this.rgb[0], 255 - this.rgb[1], 255 - this.rgb[2]];
        return this;
    }

    toRGB() {
        return this.rgb;
    }

    toHex() {
        if (!this.rgb) return false;
        let tmp2;
        let tmp = '';
        let x;
        const hex = '0123456789ABCDEF';
        for (x = 0; x < 3; x += 1) {
            tmp2 = this.rgb[x];
            tmp +=
                hex.charAt(Math.floor(tmp2 / 16)) +
                hex.charAt(Math.floor(tmp2 % 16));
        }
        return tmp;
    }
}

export default Color;

class Color {
    private rgb?: number[];

    constructor(_colorToParse: string | number[]) {
        let colorToParse = _colorToParse;
        // RGB
        if (typeof colorToParse === 'object') {
            this.rgb = colorToParse;
            return;
        }

        if (typeof colorToParse === 'string') {
            const rgbMatch = /^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i.exec(
                colorToParse,
            );
            const hexMatch = /#?[^\da-fA-F]/.exec(colorToParse);
            if (rgbMatch) {
                this.rgb = [
                    Number.parseFloat(rgbMatch[1]),
                    Number.parseFloat(rgbMatch[2]),
                    Number.parseFloat(rgbMatch[3]),
                ];
                return;
            }

            if (hexMatch) {
                if (colorToParse.startsWith('#')) {
                    colorToParse = colorToParse.slice(1);
                }
                if (colorToParse.length === 3) {
                    colorToParse =
                        colorToParse.charAt(0) +
                        colorToParse.charAt(0) +
                        colorToParse.charAt(1) +
                        colorToParse.charAt(1) +
                        colorToParse.charAt(2) +
                        colorToParse.charAt(2);
                }
                if (colorToParse.length !== 6) {
                    this.rgb = [0, 0, 0];
                    return;
                }

                this.rgb = [];
                for (let x = 0; x < 3; x += 1) {
                    this.rgb[x] = Number.parseInt(
                        colorToParse.slice(x * 2, (x + 1) * 2),
                        16,
                    );
                }
            }
        }
    }

    invert() {
        if (this.rgb) {
            this.rgb = [
                255 - this.rgb[0],
                255 - this.rgb[1],
                255 - this.rgb[2],
            ];
        }
        return this;
    }

    toRGB() {
        return this.rgb;
    }

    toHex() {
        return this.rgb
            ?.map((dec) => dec.toString(16).padStart(2, '0'))
            .join('');
    }
}

export default Color;

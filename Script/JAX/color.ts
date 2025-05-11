class Color {
    private rgb: number[];

    constructor(_colorToParse: string | number[]) {
        let colorToParse = _colorToParse;
        // RGB
        if (typeof colorToParse === 'object') {
            this.rgb = colorToParse;
            return;
        }

        if (typeof colorToParse === 'string') {
            const rgbMatch = colorToParse.match(
                /^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i,
            );
            const hexMatch = colorToParse.match(/#?[^\da-fA-F]/);
            if (rgbMatch) {
                rgbMatch.shift();
                this.rgb = [
                    parseFloat(rgbMatch[1]),
                    parseFloat(rgbMatch[2]),
                    parseFloat(rgbMatch[3]),
                ];
                return;
            }

            if (hexMatch) {
                if (colorToParse.charAt(0) === '#') {
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
                    this.rgb[x] = parseInt(
                        colorToParse.slice(x * 2, (x + 1) * 2),
                        16,
                    );
                }
            }
        }

        this.rgb = [0, 0, 0];
    }

    invert() {
        this.rgb = [255 - this.rgb[0], 255 - this.rgb[1], 255 - this.rgb[2]];
        return this;
    }

    toRGB() {
        return this.rgb;
    }

    toHex() {
        return this.rgb
            .map((dec) => dec.toString(16).padStart(2, '0'))
            .join('');
    }
}

export default Color;

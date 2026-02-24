class Color {
  private rgb?: number[];

  constructor(colorToParse: string) {
    const rgbMatch = /^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i.exec(colorToParse);
    const hexMatch = /#?([\da-fA-F]+)/.exec(colorToParse);

    if (rgbMatch) {
      this.rgb = [
        Number.parseFloat(rgbMatch[1]),
        Number.parseFloat(rgbMatch[2]),
        Number.parseFloat(rgbMatch[3]),
      ];
      return;
    }

    if (hexMatch) {
      let hexCode = hexMatch[1];

      // FFF -> FFFFFF
      if (hexCode.length === 3) {
        hexCode =
          hexCode.charAt(0) +
          hexCode.charAt(0) +
          hexCode.charAt(1) +
          hexCode.charAt(1) +
          hexCode.charAt(2) +
          hexCode.charAt(2);
      }

      // Invalid hex
      if (hexCode.length !== 6) {
        this.rgb = [0, 0, 0];
        return;
      }

      this.rgb = [];
      for (let x = 0; x < 3; x += 1) {
        this.rgb[x] = Number.parseInt(hexCode.slice(x * 2, (x + 1) * 2), 16);
      }
    }
  }

  invert() {
    if (this.rgb) {
      this.rgb = [255 - this.rgb[0], 255 - this.rgb[1], 255 - this.rgb[2]];
    }
    return this;
  }

  toRGB() {
    return this.rgb;
  }

  toHex() {
    return (
      "#" + this.rgb?.map((dec) => dec.toString(16).padStart(2, "0")).join("")
    );
  }
}

export default Color;

class Color {
  constructor (a) {
    var tmp;
    var x;
    if (a.charAt && a.charAt(0) == "#") a = a.substr(1);
    // RGB
    if (typeof a == "object") this.rgb = a;
    else if (a.match && (tmp = a.match(/^rgb\((\d+),\s?(\d+),\s?(\d+)\)/i))) {
      tmp[1] = parseFloat(tmp[1]);
      tmp[2] = parseFloat(tmp[2]);
      tmp[3] = parseFloat(tmp[3]);
      tmp.shift();
      this.rgb = tmp;
      // HEX
    } else if (a.match && !a.match(/[^\da-fA-F]/)) {
      if (a.length == 3) {
        a =
          a.charAt(0) +
          a.charAt(0) +
          a.charAt(1) +
          a.charAt(1) +
          a.charAt(2) +
          a.charAt(2);
      }
      if (a.length != 6) this.rgb = [0, 0, 0];
      else {
        this.rgb = [];
        for (x = 0; x < 3; x++) this.rgb[x] = parseInt(a.substr(x * 2, 2), 16);
      }
    } else this.rgb = [0, 0, 0];
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
    var tmp2;
    var tmp = "";
    var x;
    var hex = "0123456789ABCDEF";
    for (x = 0; x < 3; x++) {
      tmp2 = this.rgb[x];
      tmp +=
        hex.charAt(Math.floor(tmp2 / 16)) + hex.charAt(Math.floor(tmp2 % 16));
    }
    return tmp;
  }
}

export default Color;
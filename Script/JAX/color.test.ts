import Color from "./color";

test.each([
  ["fff", [255, 255, 255]],
  ["#999", [153, 153, 153]],
  ["rgb(1, 2, 3)", [1, 2, 3]],
  ["rgb(1,2,3)", [1, 2, 3]],
])("color parsing", function (color: string, rgb: number[]) {
  expect(new Color(color).toRGB()).toEqual(rgb);
});

test.each([
  [[255, 255, 255], "#ffffff"],
  [[0, 0, 0], "#000000"],
  [[255, 0, 0], "#ff0000"],
])("color parsing", function (rgb: number[], hex: string) {
  expect(new Color(`rgb(${rgb.join(",")})`).toHex()).toEqual(hex);
});

test.each([
  [[255, 255, 255], "#000000"],
  [[0, 0, 0], "#ffffff"],
  [[255, 0, 0], "#00ffff"],
])("color invert", function (rgb: number[], hex: string) {
  expect(new Color(`rgb(${rgb.join(",")})`).invert().toHex()).toEqual(hex);
});

import { animate } from "./animation";

test("animation", async function () {
  expect.assertions(2);

  // Jest does not have animation support so we mock it out
  let finish = jest.fn();
  const addEventListener = jest.fn((event, cb) => {
    if (event === "finish") finish = cb;
  });
  Element.prototype.animate = jest.fn(
    () => ({ addEventListener }) as unknown as Animation,
  );

  const div = document.createElement("div");

  const promise = animate(div, [{ height: "0px" }, { height: "100px" }], 1000);

  // Trigger animation finish
  finish();

  expect(await promise).toBe(div);

  // Style applied after animation complete
  expect(div.style.height).toBe("100px");
});

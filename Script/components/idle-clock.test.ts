import IdleClock from "./idle-clock";

test("idle clock", function () {
  const twoThirtyAM = new Date();
  twoThirtyAM.setHours(2);
  twoThirtyAM.setMinutes(30);

  const twoThirtyMS = Math.floor(twoThirtyAM.valueOf() / 1000);

  document.body.innerHTML = `<div class="idle lastAction${twoThirtyMS}">Sean</div>`;

  IdleClock.hydrate(document.body);

  expect(document.querySelector(".idle")?.innerHTML).toBe("🕝Sean");
});

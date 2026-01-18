/**
 * Animates an element's style from one set CSS properties to another.
 *
 * @param el The element to animate
 * @param from CSS properties before
 * @param to CSS properties after
 * @param durationMS Duration of the animation (in MS)
 * @param timingFunction A CSS animation timing function (ex: linear, ease-in-out)
 */
export async function animate<T extends HTMLElement>(
  el: T,
  from: Partial<CSSStyleDeclaration>,
  to: Partial<CSSStyleDeclaration>,
  durationMS = 600,
  timingFunction = "linear",
): Promise<T> {
  Object.assign(el.style, from);

  // This is needed to force a repaint
  // eslint-disable-next-line @typescript-eslint/no-unused-expressions
  el.offsetHeight;

  el.style.transition = `all ${durationMS}ms ${timingFunction}`;
  Object.assign(el.style, to);

  return new Promise<T>((res) =>
    setTimeout(() => {
      el.style.transition = "";
      res(el);
    }, durationMS),
  );
}

/**
 * Makes an element yellow for a moment to draw attention to it
 * @param el the element
 */
export function dehighlight(el: HTMLElement) {
  return animate(
    el,
    { backgroundColor: "#FF0" },
    { backgroundColor: "" },
    1000,
  );
}

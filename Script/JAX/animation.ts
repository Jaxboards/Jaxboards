/**
 * Animates an element's style from one set CSS properties to another.
 *
 * @param el The element to animate
 * @param keyframes An array of keyframes (css declarations)
 * @param duration Duration of the animation (in MS)
 * @param easing A CSS animation timing function (ex: linear, ease-in-out)
 */
export async function animate<T extends HTMLElement>(
  el: T,
  keyframes: Keyframe[],
  duration = 600,
  easing = "linear",
): Promise<T> {
  el.animate(keyframes, {
    duration,
    easing,
  });

  return new Promise<T>((res) =>
    setTimeout(() => {
      el.style.transition = "";
      res(el);
    }, duration),
  );
}

/**
 * Makes an element yellow for a moment to draw attention to it
 * @param el the element
 */
export function dehighlight(el: HTMLElement) {
  return animate(
    el,
    [{ backgroundColor: "#FF0" }, { backgroundColor: "" }],
    1000,
  );
}

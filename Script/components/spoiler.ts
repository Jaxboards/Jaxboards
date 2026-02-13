import register, { Component } from "../JAX/component";

export default class Spoiler extends Component<HTMLInputElement> {
  static hydrate(container: HTMLElement): void {
    register(
      "Spoiler",
      container.querySelectorAll<HTMLInputElement>(".spoilertext"),
      this,
    );
  }

  constructor(element: HTMLInputElement) {
    super(element);

    element.addEventListener("click", function () {
      this.classList.toggle("spoiled");
    });
  }
}

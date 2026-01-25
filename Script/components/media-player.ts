import register, { Component } from "../JAX/component";
import Window from "../JAX/window";

export default class MediaPlayer extends Component<HTMLDivElement> {
  static hydrate(container: HTMLElement): void {
    register(
      "MediaPlayer",
      container.querySelectorAll<HTMLDivElement>(".media"),
      this,
    );
  }

  constructor(element: HTMLDivElement) {
    super(element);

    const popoutLink = element.querySelector<HTMLAnchorElement>("a.popout");
    const inlineLink = element.querySelector<HTMLAnchorElement>("a.inline");
    const movie = element.querySelector<HTMLDivElement>(".movie");

    if (!movie) return;

    popoutLink?.addEventListener("click", (event) => {
      event.preventDefault();
      new Window({
        title: popoutLink.href,
        content: movie.innerHTML,
      });
    });

    inlineLink?.addEventListener("click", (event) => {
      event.preventDefault();
      movie.style.display = "block";
    });
  }
}

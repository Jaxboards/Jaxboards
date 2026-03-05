import register, { Component } from "../component";
import { toDOM } from "../dom";

export default class ImageGallery extends Component<HTMLDivElement> {
  index: number;

  images: NodeListOf<HTMLImageElement>;

  max: number;

  static hydrate(container: HTMLElement): void {
    register(
      "ImageGallery",
      container.querySelectorAll<HTMLDivElement>(".image_gallery"),
      this,
    );
  }

  constructor(element: HTMLDivElement) {
    super(element);

    const controls = toDOM<HTMLDivElement>(`<div>
      <button type="button" data-action="prev">Prev &laquo;</button>
      <button type="button" data-action="next">Next &raquo;</button>
    </div>`);
    controls.addEventListener("click", (event) => {
      if (event.target instanceof HTMLButtonElement) {
        const { action } = event.target.dataset;
        if (action === "next") {
          this.showNext();
        } else {
          this.showPrev();
        }
      }
    });

    this.index = 0;
    this.images = element.querySelectorAll("img");
    this.max = Math.max(this.images.length, 1);

    this.update();
    element.appendChild(controls);
  }

  showNext() {
    if (this.index < this.max - 1) {
      this.index += 1;
    }
    this.update();
  }

  showPrev() {
    if (this.index > 0) {
      this.index -= 1;
    }
    this.update();
  }

  update() {
    this.images.forEach((img, i) => {
      const container = img.dataset.madeResized
        ? (img.parentNode as HTMLElement)
        : img;

      if (container) {
        container.style.display = i === this.index ? "block" : "none";
      }
    });
  }
}

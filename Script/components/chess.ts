import register, { Component } from "../JAX/component";
import Drag, { DragSession } from "../JAX/drag";
import toast from "../JAX/toast";

export default class Chess extends Component<HTMLTableElement> {
  static hydrate(container: HTMLElement): void {
    register(
      "Chess",
      container.querySelectorAll<HTMLTableElement>(".chess"),
      this,
    );
  }

  constructor(element: HTMLTableElement) {
    super(element);

    const drag = new Drag();
    drag.addListener({
      ondrop: (dropEvent: DragSession) => {
        if (dropEvent.droptarget) {
          dropEvent.droptarget.querySelector(".piece")?.remove();
          dropEvent.droptarget.append(dropEvent.el);
          navigator.clipboard.writeText(
            "[chess]" + this.getFENNotation() + "[/chess]",
          );
          toast.success("BBCode copied to clipboard");
        }
        dropEvent.reset();
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));
  }

  getFENNotation() {
    const cells = Array.from(this.element.querySelectorAll("td"));

    return cells
      .map(
        (cell, index) =>
          (index && index % 8 === 0 ? "/" : "") +
          (cell.querySelector<HTMLDivElement>(".piece")?.dataset.piece || " "),
      )
      .join("")
      .replaceAll(/ +/g, (rep) => `${rep.length}`);
  }
}

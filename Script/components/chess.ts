import register, { Component } from "../JAX/component";
import Drag, { DragSession } from "../JAX/drag";

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
          dropEvent.droptarget.append(dropEvent.el);
        }
        dropEvent.reset();
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));
  }
}

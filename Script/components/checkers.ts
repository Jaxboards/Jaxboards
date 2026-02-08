import register, { Component } from "../JAX/component";
import Drag, { DragSession } from "../JAX/drag";
import toast from "../JAX/toast";

export default class Checkers extends Component<HTMLTableElement> {
  static hydrate(container: HTMLElement): void {
    register(
      "Chess",
      container.querySelectorAll<HTMLTableElement>(".checkers"),
      this,
    );
  }

  constructor(element: HTMLTableElement) {
    super(element);

    const drag = new Drag();
    drag.addListener({
      ondrop: (
        dropEvent: DragSession<HTMLDivElement, HTMLTableCellElement>,
      ) => {
        dropEvent.reset();

        if (!dropEvent.droptarget) {
          return;
        }

        const cell = dropEvent.droptarget;
        const piece = dropEvent.el;

        // handle "king" promotion
        if (
          cell.parentElement instanceof HTMLTableRowElement &&
          [1, 8].includes(cell.parentElement.rowIndex)
        ) {
          piece.dataset.piece = (piece.dataset.piece ?? "").toUpperCase();
          piece.innerHTML = "♛";
        }

        cell.querySelector(".piece")?.remove();
        cell.append(piece);

        navigator.clipboard.writeText(
          "[checkers]" + this.getGameState() + "[/checkers]",
        );
        toast.success("BBCode copied to clipboard");
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));
  }

  getGameState() {
    const cells = Array.from(this.element.querySelectorAll("td"));

    // Gotta map an 8x8 grid to a 4x4 grid
    const state: string[][] = [];

    for (let row = 0; row < 8; row++) {
      state.push([]);
      for (let col = 0; col < 4; col++) {
        const offset = row % 2 ? 0 : 1;

        state[row].push(
          cells[row * 8 + col * 2 + offset].querySelector<HTMLDivElement>(
            ".piece",
          )?.dataset.piece || " ",
        );
      }
    }

    return state
      .map((row) => row.join(""))
      .join("/")
      .replaceAll(/ +/g, (rep) => `${rep.length}`);
  }
}

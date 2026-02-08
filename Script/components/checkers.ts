import register, { Component } from "../JAX/component";
import { getCellCoordinates } from "../JAX/dom";
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
        const pieceEl = dropEvent.el;
        const piece = pieceEl.dataset.piece ?? "";

        const fromCoords = getCellCoordinates(pieceEl.closest("td"));
        const toCoords = getCellCoordinates(cell);

        if (!this.isValidMove(piece, fromCoords, toCoords)) {
          return;
        }

        // handle "king" promotion
        if ([1, 8].includes(toCoords[0])) {
          pieceEl.dataset.piece = piece.toUpperCase();
          pieceEl.innerHTML = "♛";
        }

        cell.querySelector(".piece")?.remove();
        cell.append(pieceEl);

        navigator.clipboard.writeText(
          "[checkers]" + this.getGameState() + "[/checkers]",
        );
        toast.success("BBCode copied to clipboard");
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));
  }

  isValidMove(piece = "", from: [number, number], to: [number, number]) {
    // prevent landing on white squares
    if (to[0] % 2 === to[1] % 2) {
      return false;
    }

    // prevent moving in anything but diagonal
    if (Math.abs(to[0] - from[0]) !== Math.abs(to[1] - from[1])) {
      return false;
    }

    // black pieces must move down
    if (piece === "b" && to[0] <= from[0]) {
      return false;
    }

    // red pieces must move up
    if (piece === "r" && to[0] >= from[0]) {
      return false;
    }

    return true;
  }

  getGameState() {
    const cells = Array.from(this.element.querySelectorAll("td"));

    // Gotta map an 8x8 grid to a 8x4 grid
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

import register, { Component } from "../JAX/component";
import { getCellCoordinates } from "../JAX/dom";
import Drag, { DragSession } from "../JAX/drag";
import toast from "../JAX/toast";
import sound from "../sound";

export default class Checkers extends Component<HTMLTableElement> {
  static hydrate(container: HTMLElement): void {
    register(
      "Checkers",
      container.querySelectorAll<HTMLTableElement>(".checkers"),
      this,
    );
  }

  constructor(element: HTMLTableElement) {
    super(element);

    sound.load("chessdrop", "/Sounds/chessdrop.mp3", false);

    const drag = new Drag();
    drag.addListener({
      ondrop: (
        dropEvent: DragSession<HTMLDivElement, HTMLTableCellElement>,
      ) => {
        dropEvent.reset();

        if (!dropEvent.droptarget) {
          return;
        }

        const targetCell = dropEvent.droptarget;
        const pieceEl = dropEvent.el;
        const checkerboard = pieceEl.closest("table");

        const fromCoords = getCellCoordinates(pieceEl.closest("td"));
        const toCoords = getCellCoordinates(targetCell);

        const vector = [
          toCoords[0] - fromCoords[0],
          toCoords[1] - fromCoords[1],
        ];
        const distance = vector.map(Math.abs);

        const capturedPieceEl =
          distance.join(",") === "2,2"
            ? checkerboard?.rows[fromCoords[0] + vector[0] / 2].cells[
                fromCoords[1] + vector[1] / 2
              ].querySelector<HTMLDivElement>(".piece")
            : undefined;

        const piece = pieceEl.dataset.piece ?? "";
        const capturedPiece = capturedPieceEl?.dataset.piece ?? "";

        if (
          // can't land on other pieces
          targetCell.querySelector(".piece") ||
          !this.isValidMove(
            piece,
            capturedPiece,
            fromCoords,
            toCoords,
            distance,
          )
        ) {
          return;
        }

        // handle "king" promotion
        if ([1, 8].includes(toCoords[0])) {
          pieceEl.dataset.piece = piece.toUpperCase();
          pieceEl.innerHTML = "♛";
        }

        capturedPieceEl?.remove();
        targetCell.append(pieceEl);
        sound.play("chessdrop");

        navigator.clipboard.writeText(
          "[checkers]" + this.getGameState() + "[/checkers]",
        );
        toast.success("BBCode copied to clipboard");
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));
  }

  isValidMove(
    piece = "",
    capturedPiece = "",
    from: number[],
    to: number[],
    distance: number[],
  ) {
    // prevent landing on white squares
    if (to[0] % 2 === to[1] % 2) {
      return false;
    }

    // prevent moving in anything but diagonal
    if (distance[0] != distance[1]) {
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

    // can't jump own pieces
    if (capturedPiece.toLowerCase() === piece.toLowerCase()) {
      return false;
    }

    // moved correct distance
    return distance[0] === (capturedPiece ? 2 : 1);
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

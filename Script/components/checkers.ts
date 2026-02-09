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

        const fromCoords = getCellCoordinates(pieceEl.closest("td"));
        const toCoords = getCellCoordinates(targetCell);

        if (!this.isValidMove(fromCoords, toCoords)) {
          return;
        }

        // handle "king" promotion
        if ([1, 8].includes(toCoords[0])) {
          this.promote(pieceEl);
        }

        // remove captured piece if any
        this.getCapturedPiece(fromCoords, toCoords)?.remove();

        // place the piece down
        targetCell.append(pieceEl);
        sound.play("chessdrop");
        this.moveNumber++;

        navigator.clipboard.writeText(
          "[checkers]" + this.gameState + "[/checkers]",
        );
        toast.success("BBCode copied to clipboard");
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));
  }

  getCapturedPiece(from: number[], to: number[]) {
    const vector = [to[0] - from[0], to[1] - from[1]];
    const distance = vector.map(Math.abs);

    if (distance.join(",") !== "2,2") {
      return undefined;
    }
    return (
      this.element.rows[from[0] + vector[0] / 2].cells[
        from[1] + vector[1] / 2
      ].querySelector<HTMLDivElement>(".piece") ?? undefined
    );
  }

  promote(pieceEl: HTMLDivElement) {
    pieceEl.dataset.piece = pieceEl.dataset.piece?.toUpperCase();
    pieceEl.innerHTML = "♛";
  }

  isValidMove(from: number[], to: number[]) {
    const checkerboard = this.element;

    const distance = [to[0] - from[0], to[1] - from[1]].map(Math.abs);

    const piece =
      checkerboard.rows[from[0]].cells[from[1]].querySelector<HTMLDivElement>(
        ".piece",
      )?.dataset.piece ?? "";
    const capturedPiece = this.getCapturedPiece(from, to)?.dataset.piece ?? "";

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

    // can't land on other pieces
    if (checkerboard.rows[to[0]].cells[to[1]].querySelector(".piece")) {
      return false;
    }

    // moved correct distance
    return distance[0] === (capturedPiece ? 2 : 1);
  }

  get moveNumber() {
    return Number(this.element.dataset.moveNumber ?? "1");
  }

  set moveNumber(moveNumber: number) {
    this.element.dataset.moveNumber = `${moveNumber}`;
  }

  get gameState() {
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

    return (
      state
        .map((row) => row.join(""))
        .join("/")
        .replaceAll(/ +/g, (rep) => `${rep.length}`) + ` ${this.moveNumber}`
    );
  }
}

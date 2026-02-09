import register, { Component } from "../JAX/component";
import { getCellCoordinates } from "../JAX/dom";
import Drag, { DragSession } from "../JAX/drag";
import toast from "../JAX/toast";
import sound from "../sound";

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

        const piece = dropEvent.el.dataset.piece;
        const capturedPieceEl =
          dropEvent.droptarget.querySelector<HTMLDivElement>(".piece");
        const capturedPiece = capturedPieceEl?.dataset.piece?.trim() ?? "";
        const fromCoords = getCellCoordinates(dropEvent.el.closest("td"));
        const toCoords = getCellCoordinates(dropEvent.droptarget);

        if (!this.isValidMove(piece, capturedPiece, fromCoords, toCoords)) {
          return;
        }

        capturedPieceEl?.remove();
        dropEvent.droptarget.append(dropEvent.el);
        sound.play("chessdrop");
        this.moveNumber++;

        navigator.clipboard.writeText(
          "[chess]" + this.getFENNotation() + "[/chess]",
        );

        toast.success("BBCode copied to clipboard");
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));
  }

  get moveNumber() {
    return Number(this.element.dataset.moveNumber ?? "1");
  }

  set moveNumber(moveNumber: number) {
    this.element.dataset.moveNumber = `${moveNumber}`;
  }

  didJumpAPiece(from: number[], to: number[]) {
    const vector = [to[0] - from[0], to[1] - from[1]];
    const step = vector.map(Math.sign);
    const distance = Math.max(...vector.map(Math.abs));

    for (let i = 1; i < distance; i++) {
      if (
        this.element.rows[from[0] + step[0] * i].cells[
          from[1] + step[1] * i
        ].querySelector(".piece")
      ) {
        return true;
      }
    }
    return false;
  }

  isValidMove(piece = "", capturedPiece = "", from: number[], to: number[]) {
    const vector = [from[0] - to[0], from[1] - to[1]];
    const distance = vector.map(Math.abs);
    const movedStraight = Math.min(...distance) === 0;
    const movedDiagonally = distance[0] === distance[1];

    // can't capture own pieces
    if (
      capturedPiece !== "" &&
      (piece.toLowerCase() === piece) ===
        (capturedPiece.toLowerCase() === capturedPiece)
    ) {
      return false;
    }

    switch (piece) {
      // todo: en passant
      case "p":
        return (
          from[0] < to[0] &&
          Math.max(...distance) <= (from[0] === 2 ? 2 : 1) &&
          (capturedPiece ? movedDiagonally : movedStraight)
        );
      case "P":
        return (
          from[0] > to[0] &&
          Math.max(...distance) <= (from[0] === 7 ? 2 : 1) &&
          (capturedPiece ? movedDiagonally : movedStraight)
        );

      case "r":
      case "R":
        return movedStraight && !this.didJumpAPiece(from, to);

      case "b":
      case "B":
        return movedDiagonally && !this.didJumpAPiece(from, to);

      case "n":
      case "N":
        return distance.toSorted((a, b) => a - b).join(",") === "1,2";

      case "q":
      case "Q":
        return (
          (movedStraight || movedDiagonally) && !this.didJumpAPiece(from, to)
        );

      case "k":
      case "K":
        // TODO: castling
        return Math.max(...distance) <= 2 && (movedDiagonally || movedStraight);
    }
    return true;
  }

  getFENNotation() {
    const cells = Array.from(this.element.querySelectorAll("td"));

    return (
      cells
        .map(
          (cell, index) =>
            (index && index % 8 === 0 ? "/" : "") +
            (cell.querySelector<HTMLDivElement>(".piece")?.dataset.piece ||
              " "),
        )
        .join("")
        .replaceAll(/ +/g, (rep) => `${rep.length}`) + ` ${this.moveNumber}`
    );
  }
}

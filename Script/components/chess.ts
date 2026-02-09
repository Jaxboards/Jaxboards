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

        navigator.clipboard.writeText(
          "[chess]" + this.getFENNotation() + "[/chess]",
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
    from: [number, number],
    to: [number, number],
  ) {
    const movedStraight = from[0] === to[0] || from[1] === to[1];
    const movedDiagonally =
      Math.abs(from[0] - to[0]) === Math.abs(from[1] - to[1]);
    const distance = Math.max(
      Math.abs(from[0] - to[0]),
      Math.abs(from[1] - to[1]),
    );

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
          distance <= (from[0] === 2 ? 2 : 1) &&
          (capturedPiece ? movedDiagonally : movedStraight && from[0] < to[0])
        );
      case "P":
        return (
          distance <= (from[0] === 7 ? 2 : 1) &&
          (capturedPiece ? movedDiagonally : movedStraight && from[0] > to[0])
        );

      case "r":
      case "R":
        return movedStraight;

      case "b":
      case "B":
        return movedDiagonally;

      case "n":
      case "N":
        return (
          [Math.abs(from[0] - to[0]), Math.abs(from[1] - to[1])]
            .toSorted()
            .join(",") === "1,2"
        );

      case "q":
      case "Q":
        return movedStraight || movedDiagonally;

      case "k":
      case "K":
        // TODO: castling
        return distance <= 2 && (movedDiagonally || movedStraight);
    }
    return true;
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

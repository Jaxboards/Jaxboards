import createSnow from "../eggs/snow";
import register, { Component } from "../JAX/component";
import { getCellCoordinates } from "../JAX/dom";
import Drag, { DragSession } from "../JAX/drag";
import toast from "../JAX/toast";
import sound from "../sound";

const pieceUnicode: Record<string, string> = {
  R: "♜",
  N: "♞",
  B: "♝",
  Q: "♛",
  K: "♚",
  P: "♟",
  r: "♜",
  n: "♞",
  b: "♝",
  q: "♛",
  k: "♚",
  p: "♟",
};

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

    sound.load("chessdrop");

    const drag = new Drag();
    drag.addListener({
      ondrop: (
        dropEvent: DragSession<HTMLDivElement, HTMLTableCellElement>,
      ) => {
        dropEvent.reset();

        if (!dropEvent.droptarget) {
          return;
        }

        const pieceEl = dropEvent.el;
        const piece = pieceEl.dataset.piece ?? "";
        const capturedPieceEl =
          dropEvent.droptarget.querySelector<HTMLDivElement>(".piece");
        const capturedPiece = capturedPieceEl?.dataset.piece?.trim() ?? "";
        const fromCoords = getCellCoordinates(pieceEl.closest("td"));
        const toCoords = getCellCoordinates(dropEvent.droptarget);
        const isBlack = piece.toLowerCase() === piece;

        // not your turn!
        if ((this.moveNumber % 2 === 0) !== isBlack) {
          toast.error(`It's ${isBlack ? "white" : "black"}'s turn!`, 3000);
          return;
        }

        if (!this.isValidMove(fromCoords, toCoords)) {
          return;
        }

        if (piece?.toLowerCase() === "p" && [1, 8].includes(toCoords[0])) {
          this.promotePawn(pieceEl);
        }

        // GG!
        if (capturedPiece?.toLowerCase() === "k") {
          sound.loadAndPlay("malo-mart");
          createSnow(2000, true);
        }

        capturedPieceEl?.remove();
        dropEvent.droptarget.append(pieceEl);
        sound.play("chessdrop");
        this.moveNumber++;
        this.computeDanger();

        navigator.clipboard.writeText(
          "[chess]" + this.getFENNotation() + "[/chess]",
        );

        toast.success("BBCode copied to clipboard");
      },
    });
    drag.drops(Array.from(element.querySelectorAll("td")));
    drag.apply(Array.from(element.querySelectorAll(".piece")));

    // Compute initial danger state
    this.computeDanger();
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
      if (this.getPieceAt([from[0] + step[0] * i, from[1] + step[1] * i])) {
        return true;
      }
    }
    return false;
  }

  isValidMove(from: number[], to: number[]) {
    const piece = this.getPieceAt(from)?.dataset.piece ?? "";
    const capturedPiece = this.getPieceAt(to)?.dataset.piece ?? "";
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
          (capturedPiece
            ? movedDiagonally && Math.max(...distance) == 1
            : movedStraight && Math.max(...distance) <= (from[0] === 2 ? 2 : 1))
        );
      case "P":
        return (
          from[0] > to[0] &&
          (capturedPiece
            ? movedDiagonally && Math.max(...distance) == 1
            : movedStraight && Math.max(...distance) <= (from[0] === 7 ? 2 : 1))
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
        if (!(movedDiagonally || movedStraight)) {
          return false;
        }

        if (Math.max(...distance) === 2) {
          return this.doCastle(from, to);
        }

        return Math.max(...distance) === 1;
    }
    return true;
  }

  doCastle(from: number[], to: number[]): boolean {
    const maybeSwapWithRook = (row: number, column: number, rook: string) => {
      const maybeRook = this.getPieceAt([row, column]);
      if (maybeRook?.dataset.piece !== rook) {
        return false;
      }

      if (this.didJumpAPiece(from, [row, column])) {
        return false;
      }

      // move the rook
      this.element.rows[row].cells[column === 1 ? 4 : 6].append(maybeRook);
      return true;
    };

    // black castle
    if (from[0] === 1 && from[1] === 5) {
      if (to[1] == 7) {
        return maybeSwapWithRook(from[0], 8, "r");
      }

      if (to[1] === 3) {
        return maybeSwapWithRook(from[0], 1, "r");
      }
    }

    // white castle (has delicious burgers)
    if (from[0] === 8 && from[1] === 5) {
      if (to[1] == 7) {
        return maybeSwapWithRook(from[0], 8, "R");
      }

      if (to[1] === 3) {
        return maybeSwapWithRook(from[0], 1, "R");
      }
    }

    // castle not possible
    return false;
  }

  promotePawn(pieceEl: HTMLDivElement) {
    while (true) {
      let promoteTo = (
        prompt(
          "Congrats on your promotion, sir pawn! What rank would you like? (q: Queen, r: Rook, b: Bishop, n: Knight)",
          "q",
        ) ?? ""
      ).toLowerCase();

      if (["q", "r", "b", "n"].includes(promoteTo)) {
        // ensure correct team
        promoteTo =
          pieceEl.dataset.piece === "p"
            ? promoteTo.toLowerCase()
            : promoteTo.toUpperCase();

        pieceEl.dataset.piece = promoteTo;
        pieceEl.innerHTML = pieceUnicode[promoteTo];
        sound.loadAndPlay("zelda-item-get");

        break;
      }
    }
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

  getPieceAt([row, column]: number[]) {
    return this.element.rows[row].cells[column].querySelector<HTMLDivElement>(
      ".piece",
    );
  }

  computeDanger() {
    // clear existing danger
    this.element
      .querySelectorAll<HTMLDivElement>(".piece.danger")
      .forEach((piece) => piece.classList.remove("danger"));

    const whitePieces =
      this.element.querySelectorAll<HTMLDivElement>(".piece.white");
    const blackPieces =
      this.element.querySelectorAll<HTMLDivElement>(".piece.black");

    whitePieces.forEach((whitePiece) => {
      blackPieces.forEach((blackPiece) => {
        if (this.isPieceInDanger(whitePiece, blackPiece)) {
          console.log("white in danger", blackPiece, whitePiece);

          whitePiece.classList.add("danger");
        }
        if (this.isPieceInDanger(blackPiece, whitePiece)) {
          console.log("black in danger", blackPiece, whitePiece);
          blackPiece.classList.add("danger");
        }
      });
    });
  }

  isPieceInDanger(piece: HTMLDivElement, otherPiece: HTMLDivElement) {
    const pieceCoordinates = getCellCoordinates(piece.closest("td"));
    const otherCoordinates = getCellCoordinates(otherPiece.closest("td"));

    return this.isValidMove(otherCoordinates, pieceCoordinates);
  }
}

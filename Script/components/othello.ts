import register, { Component } from "../JAX/component";
import { getCellCoordinates, toDOM } from "../JAX/dom";
import toast from "../JAX/toast";
import sound from "../sound";

const directions = [
  [-1, 0], // north
  [1, 0], // south
  [0, 1], // east
  [0, -1], // west
  [-1, 1], // northeast
  [-1, -1], // northwest
  [1, -1], // southwest
  [1, 1], // southeast
];

export default class Othello extends Component<HTMLTableElement> {
  static hydrate(container: HTMLElement): void {
    register(
      "Othello",
      container.querySelectorAll<HTMLTableElement>(".othello"),
      this,
    );
  }

  constructor(element: HTMLTableElement) {
    super(element);

    element.addEventListener("click", (event) => {
      if (
        event.target instanceof HTMLTableCellElement &&
        !event.target.querySelector(".piece")
      ) {
        if (this.placePiece(event.target)) {
          this.moveNumber++;
        }
      }
    });

    sound.load("chessdrop");
  }

  placePiece(cell: HTMLTableCellElement): boolean {
    const myColor = this.moveNumber % 2 ? "black" : "white";
    const oppositeColor = this.moveNumber % 2 ? "white" : "black";

    // traverse outwards in all 8 directions and record any pieces that need flipping
    const flippable: HTMLDivElement[][] = Array.from({ length: 8 }, () => []);
    const done = Array.from({ length: 8 }, () => false);

    const coords = getCellCoordinates(cell);

    for (let i = 1; i <= 8; i++) {
      let piece: HTMLDivElement | undefined;

      for (let direction = 0; direction < 8; direction++) {
        if (done[direction]) {
          continue;
        }

        piece = this.getPieceAt(
          coords[0] + i * directions[direction][0],
          coords[1] + i * directions[direction][1],
        );

        // got to the edge of the board and did not find same color
        // clear pieces found in that direction
        if (!piece) {
          done[direction] = true;
          flippable[direction] = [];
          continue;
        }

        // found opposite color, add it to flippables
        if (piece.classList.contains(oppositeColor)) {
          flippable[direction].push(piece);
          continue;
        }

        // found same color, stop traversing
        if (piece.classList.contains(myColor)) {
          done[direction] = true;
        }
      }
    }

    const toFlip = flippable.flat();

    // Invalid move! Placed pieces _must_ capture.
    if (!toFlip.length) {
      toast.error("Invalid move! You must capture opposing pieces.");
      return false;
    }

    toFlip.forEach((piece) => {
      piece.classList.add(myColor);
      piece.classList.remove(oppositeColor);
    });

    cell.append(toDOM(`<div class="piece ${myColor}"></div>`));
    return true;
  }

  get moveNumber() {
    return Number(this.element.dataset.moveNumber ?? "1");
  }

  set moveNumber(moveNumber: number) {
    this.element.dataset.moveNumber = `${moveNumber}`;
  }

  isValidMove() {
    return true;
  }

  getPieceAt(row: number, column: number) {
    if (row < 0 || column < 0 || row >= 8 || column >= 8) return;

    return (
      this.element.rows[row].cells[column].querySelector<HTMLDivElement>(
        ".piece",
      ) ?? undefined
    );
  }

  getGameState() {
    return "";
  }
}

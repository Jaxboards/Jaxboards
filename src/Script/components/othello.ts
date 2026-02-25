import register, { Component } from "../component";
import { type Coordinates, getCellCoordinates, toDOM } from "../dom";
import sound from "../sound";
import toast from "../toast";

const directions: Coordinates[] = [
  [-1, 0], // north
  [1, 0], // south
  [0, 1], // east
  [0, -1], // west
  [-1, 1], // northeast
  [-1, -1], // northwest
  [1, -1], // southwest
  [1, 1], // southeast
] as const;

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
        !(event.target instanceof HTMLTableCellElement) ||
        event.target.querySelector(".piece")
      ) {
        return;
      }

      if (!this.placePiece(event.target)) {
        return;
      }

      this.updateScore();
      this.moveNumber++;
      sound.play("chessdrop");

      navigator.clipboard.writeText(
        `[othello]${this.getGameState()}[/othello]`,
      );

      toast.success("BBCode copied to clipboard");
    });

    this.updateScore();

    sound.load("chessdrop");
  }

  placePiece(cell: HTMLTableCellElement): boolean {
    const myColor = this.moveNumber % 2 ? "black" : "white";
    const oppositeColor = this.moveNumber % 2 ? "white" : "black";

    // traverse outwards in all 8 directions and record any pieces that need flipping
    const flippable: HTMLDivElement[][] = Array.from({ length: 8 }, () => []);
    const done = Array.from({ length: 8 }, () => false);

    const [row, col] = getCellCoordinates(cell);

    // OOB check
    if (row < 0 || col < 0 || row >= 8 || col >= 8) return false;

    for (let i = 1; i <= 8; i++) {
      let piece: HTMLDivElement | undefined;

      for (let direction = 0; direction < 8; direction++) {
        if (done[direction]) {
          continue;
        }

        piece = this.getPieceAt([
          row + i * directions[direction][0],
          col + i * directions[direction][1],
        ]);

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
      toast.error("Invalid move! You must capture opposing pieces.", 3000);
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

  getPieceAt([row, col]: Coordinates) {
    // OOB check
    if (row < 0 || col < 0 || row >= 8 || col >= 8) return;

    return (
      this.element.rows[row].cells[col].querySelector<HTMLDivElement>(
        ".piece",
      ) ?? undefined
    );
  }

  updateScore() {
    const scoreWhite = this.element.querySelector(".score-white");
    const scoreBlack = this.element.querySelector(".score-black");

    if (scoreWhite && scoreBlack) {
      scoreWhite.innerHTML =
        this.element.querySelectorAll(".piece.white").length + "";
      scoreBlack.innerHTML =
        this.element.querySelectorAll(".piece.black").length + "";
    }
  }

  getGameState() {
    const state: string[] = [];

    for (let row = 0; row < 8; row++) {
      state.push("");

      for (let col = 0; col < 8; col++) {
        const piece = this.getPieceAt([row, col]);
        if (!piece) {
          state[row] += " ";
        } else if (piece.classList.contains("white")) {
          state[row] += "w";
        } else if (piece.classList.contains("black")) {
          state[row] += "b";
        }
      }
    }

    return [
      state.join("/").replaceAll(/ +/g, (match) => `${match.length}`),
      this.moveNumber,
    ].join(" ");
  }
}

import register, { Component } from "../component";
import { selectAll } from "../selection";

export default class CodeBlock extends Component<HTMLDivElement> {
  static hydrate(container: HTMLElement) {
    register(
      "CodeBlock",
      container.querySelectorAll<HTMLDivElement>(".bbcode.code"),
      this,
    );
  }

  constructor(element: HTMLDivElement) {
    super(element);

    // Make BBCode code blocks selectable when clicked
    element.addEventListener("click", () => selectAll(element));
  }
}

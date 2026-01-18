import register, { Component } from "../JAX/component";

const ACTIVE_CLASS = "active";

export default class Tabs extends Component<HTMLDivElement> {
  static hydrate(container: HTMLElement): void {
    register("Tabs", container.querySelectorAll<HTMLDivElement>(".tabs"), this);
  }

  constructor(element: HTMLDivElement) {
    super(element);

    element.addEventListener("click", (event) => this.click(event));
  }

  click(event: MouseEvent) {
    const { tabSelector } = this.element.dataset;

    const { target } = event;
    if (
      !target ||
      !(target instanceof HTMLElement) ||
      target.tagName.toLowerCase() !== "a"
    )
      return;

    let container: HTMLElement = target;

    if (tabSelector) {
      const parent = target.closest<HTMLElement>(tabSelector);
      if (parent) container = parent;
    }
    const activeTab = this.element.querySelector(`.${ACTIVE_CLASS}`);
    if (activeTab) {
      activeTab.classList.remove(ACTIVE_CLASS);
    }
    container.className = ACTIVE_CLASS;
    container.blur();
  }
}

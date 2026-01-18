import register, { Component } from "../JAX/component";
import toolTip from "../JAX/tooltip";

export default class Link extends Component<HTMLAnchorElement> {
  static hydrate(container: HTMLElement): void {
    register("Link", container.querySelectorAll("a"), this);
  }

  constructor(link: HTMLAnchorElement) {
    super(link);

    // Special rules for all links
    // Handle links with tooltips
    if (link.dataset.useTooltip) {
      link.addEventListener("mouseover", () => toolTip(link));
    }

    // Make all links load through AJAX
    if (!link.href) {
      return;
    }

    const href = link.getAttribute("href");
    const isLocalLink =
      !link.target && href && (href.startsWith("/") || href.startsWith("?"));

    const isJavascriptLink = href?.startsWith("javascript");

    if (isJavascriptLink) {
      return;
    }

    if (isLocalLink) {
      const oldclick = link.onclick;
      link.onclick = null;
      link.addEventListener("click", (event: PointerEvent) => {
        event.preventDefault();
        // Some links have an onclick that returns true/false based on whether
        // or not the link should execute.
        if (!oldclick || oldclick.call(link, event) !== false) {
          RUN.stream.location(href);
        }
      });

      return;
    }

    // Open external links in a new window
    link.target = "_blank";
  }
}

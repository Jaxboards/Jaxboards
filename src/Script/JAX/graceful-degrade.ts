import Components from "../components/index";
import { updateDates } from "./util";

export default function gracefulDegrade(container: HTMLElement) {
  updateDates();

  // Hydrate all components
  Components.forEach((Component) => {
    Component.hydrate(container);
  });
}

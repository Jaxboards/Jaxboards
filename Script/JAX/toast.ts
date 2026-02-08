import { animate } from "./animation";
import { getHighestZIndex } from "./dom";

const TOP_SPACING = "50px";

class Toast {
  success(message: string) {
    this.showToast(message, "success", 3000);
  }

  error(message: string) {
    this.showToast(message, "error");
  }

  private async showToast(
    message: string,
    className: string,
    timeout = 0,
  ): Promise<void> {
    const toast = document.createElement("div");
    const close = async () => {
      await animate(toast, [{ top: TOP_SPACING }, { top: `-${TOP_SPACING}` }]);
      toast.remove();
    };

    toast.classList.add(className);
    Object.assign(toast.style, {
      position: "fixed",

      // center
      left: "50%",
      transform: "translateX(-50%)",
      boxShadow: "black 5px 5px 10px",

      zIndex: getHighestZIndex(),
    });
    toast.addEventListener("click", close, { once: true });

    toast.innerHTML = message;
    const closeButton = document.createElement("a");

    closeButton.href = "javascript:void(0)";
    closeButton.innerHTML = " [X]";
    closeButton.title = "close notification";
    closeButton.addEventListener("click", close, { once: true });

    toast.append(closeButton);
    document.body.appendChild(toast);

    await animate(
      toast,
      [{ top: "0" }, { top: TOP_SPACING }],
      600,
      "ease-in-out",
    );

    if (timeout) {
      await new Promise((res) => setTimeout(res, timeout));
      close();
    }
  }
}

export default new Toast();

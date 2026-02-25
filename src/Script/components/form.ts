/* global RUN */

import register, { Component } from "../component";

export type HTMLFormWithSubmit = HTMLFormElement & {
  submitButton: HTMLButtonElement;
};

export default class Form extends Component<HTMLFormWithSubmit> {
  private readonly resetOnSubmit: boolean;

  static hydrate(container: HTMLElement): void {
    register(
      "Form",
      container.querySelectorAll<HTMLFormElement>("form[data-ajax-form]"),
      this,
    );
  }

  constructor(element: HTMLFormWithSubmit) {
    super(element);
    this.resetOnSubmit = element.dataset.ajaxForm === "resetOnSubmit";
    element.addEventListener("submit", (event) => {
      event.preventDefault();
      this.submitForm();
    });
  }

  submitForm() {
    const { element, resetOnSubmit } = this;
    const { submitButton } = element;

    const formData = new FormData(element, submitButton);

    // Filter out input[type=file]
    const withoutFiles = Array.from(formData.entries()).filter(
      (tuple): tuple is [string, string] => typeof tuple[1] === "string",
    );

    void RUN.stream.load(element.action || globalThis.location.toString(), {
      body: new URLSearchParams(withoutFiles),
    });
    if (resetOnSubmit) {
      element.reset();
    }
    RUN.stream.pollData();
  }
}

module.exports = class Controller {
  constructor(inject) {
    this.compiledTemplate = inject(`views/${this.template}`);
  }

  async render(query) {
    return this.compiledTemplate(await this.model(query));
  }
};

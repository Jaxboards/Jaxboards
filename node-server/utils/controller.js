const Handlebars = require('handlebars');

module.exports = class Controller {
  constructor(inject) {
    this.compiledTemplate = inject(`views/${this.template}`);
  }

  static get template() {
    return '';
  }

  get template() {
    return this.constructor.template;
  }

  // eslint-disable-next-line class-methods-use-this
  model(/* query */) {
    return {};
  }

  async render(ctx, childControllers = []) {
    const properties = {};

    if (childControllers.length) {
      const [childController, ...nextControllers] = childControllers;
      properties.outlet = new Handlebars.SafeString(
        await childController.render(ctx, nextControllers)
      );
    }

    Object.assign(properties, await this.model(ctx));

    return this.compiledTemplate(properties);
  }
};

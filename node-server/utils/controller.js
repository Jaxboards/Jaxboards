const Handlebars = require('handlebars');
const { inject } = require('../injections');

module.exports = class Controller {
  constructor() {
    this.compiledTemplate = inject(`views/${this.template}`);
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

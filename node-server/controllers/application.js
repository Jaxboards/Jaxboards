const Controller = require('../utils/controller');

class ApplicationController extends Controller {
  // eslint-disable-next-line class-methods-use-this
  model(ctx) {
    return {
      themePath: '/Themes/Default/css.css',
      user: ctx.state.user
    };
  }

  static get template() {
    return 'application';
  }
}

module.exports = ApplicationController;

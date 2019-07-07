const Controller = require('../utils/controller');

class ApplicationController extends Controller {
  // eslint-disable-next-line class-methods-use-this
  model() {
    return {
      themePath: '/Themes/Default/css.css'
    };
  }

  static get template() {
    return 'application';
  }
}

module.exports = ApplicationController;

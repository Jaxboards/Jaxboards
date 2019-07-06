const Controller = require('../utils/controller');

class ApplicationController extends Controller {
  // eslint-disable-next-line class-methods-use-this
  model() {
    return {
      themePath: '/Themes/Default/css.css'
    };
  }

  // eslint-disable-next-line class-methods-use-this
  get template() {
    return 'application';
  }
}

module.exports = ApplicationController;

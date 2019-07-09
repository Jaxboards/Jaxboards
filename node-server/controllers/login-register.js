const Controller = require('../utils/controller');

class LoginRegisterController extends Controller {
  // eslint-disable-next-line class-methods-use-this
  model() {}

  static get template() {
    return 'login-register';
  }
}

module.exports = LoginRegisterController;

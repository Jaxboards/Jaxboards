/* eslint-disable global-require, import/no-dynamic-require */

const fs = require('fs');
const Handlebars = require('handlebars');
const path = require('path');
const glob = require('glob');

const VIEWS_PATH = 'views';
const PARTIALS_PATH = 'partials';
const CONTROLLERS_PATH = 'controllers';
const RESOURCES_PATH = 'resources';

const VIEWS_PATH_FULL = path.join(__dirname, VIEWS_PATH);
const PARTIALS_PATH_FULL = path.join(VIEWS_PATH_FULL, PARTIALS_PATH);
const CONTROLLERS_PATH_FULL = path.join(__dirname, CONTROLLERS_PATH);
const RESOURCES_PATH_FULL = path.join(__dirname, RESOURCES_PATH);

const injections = {};

/**
 * This class will initialize a singleton only when it is needed (accessed)
 */
class LazySingleton {
  constructor(klass) {
    this.Class = klass;
  }

  get(inject) {
    if (!this.instance) {
      this.instance = new this.Class(inject);
    }
    return this.instance;
  }
}

// eslint-disable-next-line no-underscore-dangle
function _inject(injectionPath) {
  const injection = injections[injectionPath];
  if (injection instanceof LazySingleton) {
    injections[injectionPath] = injection.get(_inject);
  }
  return injections[injectionPath];
}

function loadTemplates() {
  function compileView(file) {
    return Handlebars.compile(
      fs.readFileSync(path.join(VIEWS_PATH_FULL, `${file}.hbs`), 'utf8')
    );
  }

  function registerHelpers() {
    Handlebars.registerHelper(
      'date',
      date =>
        // TODO: Implement full functionality of JAX->date
        date
    );
  }

  function registerRouteTemplates() {
    glob.sync(path.join(VIEWS_PATH_FULL, '*.hbs')).forEach(file => {
      const fileName = path.basename(file, '.hbs');
      injections[`${VIEWS_PATH}/${fileName}`] = compileView(fileName);
    });
  }

  function registerPartials() {
    glob.sync(path.join(PARTIALS_PATH_FULL, '*.hbs')).forEach(file => {
      const fileName = path.basename(file, '.hbs');
      injections[`${PARTIALS_PATH}/${fileName}`] = Handlebars.registerPartial(
        fileName,
        compileView(`${PARTIALS_PATH}/${fileName}`)
      );
    });
  }

  registerHelpers();
  registerPartials();
  registerRouteTemplates();
}

function loadControllers() {
  glob.sync(path.join(CONTROLLERS_PATH_FULL, '*.js')).forEach(file => {
    const fileName = path.basename(file, '.js');
    injections[`${CONTROLLERS_PATH}/${fileName}`] = new LazySingleton(
      require(file)
    );
  });
}

function loadResources() {
  glob.sync(path.join(RESOURCES_PATH_FULL, '*.js')).forEach(file => {
    const fileName = path.basename(file, '.js');
    injections[`${RESOURCES_PATH}/${fileName}`] = new LazySingleton(
      require(file)
    );
  });
}

loadResources();
loadControllers();
loadTemplates();

module.exports = { inject: _inject };

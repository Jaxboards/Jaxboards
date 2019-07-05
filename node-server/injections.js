/* eslint-disable global-require, import/no-dynamic-require */

const fs = require('fs');
const Handlebars = require('handlebars');
const path = require('path');
const glob = require('glob');

const VIEWS_PATH = 'views';
const PARTIALS_PATH = 'partials';
const CONTROLLERS_PATH = 'controllers';
const RESOURCES_PATH = 'resources';
const HELPERS_PATH = 'helpers';

const VIEWS_PATH_FULL = path.join(__dirname, VIEWS_PATH);
const PARTIALS_PATH_FULL = path.join(VIEWS_PATH_FULL, PARTIALS_PATH);
const HELPERS_PATH_FULL = path.join(VIEWS_PATH_FULL, HELPERS_PATH);
const CONTROLLERS_PATH_FULL = path.join(__dirname, CONTROLLERS_PATH);
const RESOURCES_PATH_FULL = path.join(__dirname, RESOURCES_PATH);

const injections = {};

function register(p, injection) {
  injections[p] = injection;
}

/**
 * This class will initialize a singleton only when it is injected
 */
class LazySingleton {
  constructor(dependency) {
    this.dependency = dependency;
  }

  get(inject) {
    if (!this.instance) {
      const Klass = require(this.dependency);
      this.instance = new Klass(inject);
    }
    return this.instance;
  }
}

const isNotTestFile = fileName => !/\.test\.js/i.test(fileName);

// eslint-disable-next-line no-underscore-dangle
function _inject(injectionPath) {
  const injection = injections[injectionPath];
  if (injection instanceof LazySingleton) {
    register(injectionPath, injection.get(_inject));
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
    glob
      .sync(path.join(HELPERS_PATH_FULL, '*.js'))
      .filter(isNotTestFile)
      .forEach(file => {
        const fileName = path.basename(file, '.js');
        Handlebars.registerHelper(fileName, require(file));
      });
  }

  function registerRouteTemplates() {
    glob.sync(path.join(VIEWS_PATH_FULL, '*.hbs')).forEach(file => {
      const fileName = path.basename(file, '.hbs');
      register(`${VIEWS_PATH}/${fileName}`, compileView(fileName));
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
  glob
    .sync(path.join(CONTROLLERS_PATH_FULL, '*.js'))
    .filter(isNotTestFile)
    .forEach(file => {
      const fileName = path.basename(file, '.js');
      register(`${CONTROLLERS_PATH}/${fileName}`, new LazySingleton(file));
    });
}

function loadResources() {
  glob
    .sync(path.join(RESOURCES_PATH_FULL, '*.js'))
    .filter(isNotTestFile)
    .forEach(file => {
      const fileName = path.basename(file, '.js');
      register(`${RESOURCES_PATH}/${fileName}`, new LazySingleton(file));
    });
}

function loadAll() {
  loadResources();
  loadControllers();
  loadTemplates();
}

module.exports = { inject: _inject, register, loadAll };

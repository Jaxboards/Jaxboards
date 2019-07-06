/* eslint-disable global-require, import/no-dynamic-require */

const fs = require('fs');
const Handlebars = require('handlebars');
const path = require('path');
const glob = require('glob');

const TYPE = {
  VIEW: 'views',
  PARTIAL: 'partials',
  CONTROLLER: 'controllers',
  RESOURCE: 'resources',
  HELPER: 'helpers',
  MODEL: 'models'
};

const PATHS = {
  VIEWS: path.join(__dirname, TYPE.VIEW, '*.hbs'),
  PARTIALS: path.join(__dirname, TYPE.VIEW, TYPE.PARTIAL, '*.hbs'),
  HELPERS: path.join(__dirname, TYPE.VIEW, TYPE.HELPER, '*.js'),
  CONTROLLERS: path.join(__dirname, TYPE.CONTROLLER, '*.js'),
  RESOURCES: path.join(__dirname, TYPE.RESOURCE, '*.js'),
  MODELS: path.join(__dirname, TYPE.MODEL, '*.js')
};

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
    return Handlebars.compile(fs.readFileSync(file, 'utf8'));
  }

  function registerHelpers() {
    glob
      .sync(PATHS.HELPERS)
      .filter(isNotTestFile)
      .forEach(file => {
        const fileName = path.basename(file, '.js');
        Handlebars.registerHelper(fileName, require(file));
      });
  }

  function registerRouteTemplates() {
    glob.sync(PATHS.VIEWS).forEach(file => {
      const fileName = path.basename(file, '.hbs');
      register(`${TYPE.VIEW}/${fileName}`, compileView(file));
    });
  }

  function registerPartials() {
    glob.sync(PATHS.PARTIALS).forEach(file => {
      const fileName = path.basename(file, '.hbs');
      Handlebars.registerPartial(fileName, compileView(file));
    });
  }

  registerHelpers();
  registerPartials();
  registerRouteTemplates();
}

function loadControllers() {
  glob
    .sync(PATHS.CONTROLLERS)
    .filter(isNotTestFile)
    .forEach(file => {
      const fileName = path.basename(file, '.js');
      register(`${TYPE.CONTROLLER}/${fileName}`, new LazySingleton(file));
    });
}

function loadModels() {
  glob
    .sync(PATHS.MODELS)
    .filter(isNotTestFile)
    .forEach(file => {
      const fileName = path.basename(file, '.js');
      register(`${TYPE.MODEL}/${fileName}`, require(file));
    });
}

function loadResources() {
  glob
    .sync(PATHS.RESOURCES)
    .filter(isNotTestFile)
    .forEach(file => {
      const fileName = path.basename(file, '.js');
      register(`${TYPE.RESOURCE}/${fileName}`, new LazySingleton(file));
    });
}

function loadRouter() {
  const routes = require('./routes');
  register('router', routes());
}

function loadAll() {
  loadModels();
  loadResources();
  loadControllers();
  loadTemplates();
  loadRouter();
}

function queryByType(type) {
  return Object.keys(injections).filter(injection =>
    injection.startsWith(type)
  );
}

module.exports = { inject: _inject, register, loadAll, queryByType, TYPE };

const { inject, loadAll, register } = require('../injections');
const routes = require('../routes');

// Load all injections and do additional registrations
loadAll();
register('router', routes());

module.exports = function injectionMocker(injections = {}) {
  return path => injections[path] || inject(path);
};

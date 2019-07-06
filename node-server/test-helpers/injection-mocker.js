const { inject, loadAll } = require('../injections');

// Load all injections and do additional registrations
loadAll();

module.exports = function injectionMocker(injections = {}) {
  return path => injections[path] || inject(path);
};

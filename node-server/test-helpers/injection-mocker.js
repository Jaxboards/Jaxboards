const { inject } = require('../injections');

module.exports = function injectionMocker(injections = {}) {
  return path => injections[path] || inject(path);
};

module.exports = function(...strings) {
  strings.pop(); // pop off options
  return strings.join('');
};

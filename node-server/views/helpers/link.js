const Handlebars = require('handlebars');
const { inject } = require('../../injections');

module.exports = function linkHelper(what, ...args) {
  let id;
  let options;

  // ID is a positional argument, and is optional.
  if (args.length === 1) {
    id = null;
    [options] = args;
  } else if (args.length === 2) {
    [id, options] = args;
  }

  const { queryParams, ...htmlAttributesObj } = options.hash;

  const urlArgs = [what];
  if (id) {
    urlArgs.push({ id });
  }
  urlArgs.push({ query: queryParams });

  const path = inject('router').url(...urlArgs);

  // Add attributes to the <a> tag
  let htmlAttributes = '';
  if (htmlAttributesObj) {
    htmlAttributes = Object.entries(htmlAttributesObj)
      .map(([key, value]) => `${key}="${value}"`)
      .join(' ');
  }

  return new Handlebars.SafeString(
    `<a href="${path}" ${htmlAttributes}>${options.fn(this).trim()}</a>`
  );
};

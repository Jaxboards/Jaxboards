const Handlebars = require('handlebars');
const { inject } = require('../../injections');

module.exports = function linkHelper(what, id, options) {
  const { queryParams, ...htmlAttributesObj } = options.hash;

  if (!id) {
    throw new Error(`Missing id for {{#link "${what}"}}`);
  }

  const path = inject('router').url(what, { id }, { query: queryParams });

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

const Handlebars = require('handlebars');

module.exports = function linkHelper(what, options) {
  const { id } = options.hash;
  let path;
  switch (what) {
    case 'forum':
      path = `/forum/${id}`;
      break;
    case 'topic':
      path = `/topic/${id}${options.hash.getLast ? '?getLast=true' : ''}`;
      break;
    default:
      path = '';
  }

  return new Handlebars.SafeString(
    `<a href="${path}">${options.fn(this).trim()}</a>`
  );
};

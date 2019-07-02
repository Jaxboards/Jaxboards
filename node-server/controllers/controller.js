const Handlebars = require('handlebars');
const fs = require('fs');
const path = require('path');

const VIEWS_PATH = path.join(__dirname, '../views');

function compileView(file) {
  return Handlebars.compile(
    fs.readFileSync(path.join(VIEWS_PATH, `${file}.hbs`), 'utf8')
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

function registerPartials() {
  const partialsPath = 'partials';
  fs.readdir(path.join(VIEWS_PATH, partialsPath), (err, files) => {
    files.forEach(file => {
      const fileName = path.basename(file, '.hbs');
      Handlebars.registerPartial(
        fileName,
        compileView(`${partialsPath}/${fileName}`)
      );
    });
  });
}

module.exports = class Controller {
  async render(query) {
    // TODO: Move template compilation to earlier step
    // and out of controller base class
    registerHelpers();
    registerPartials();

    // TODO: remove true to cache compiled templates
    // It's helpful for dev, maybe make dev flag?
    if (true || !this.compiledTemplate) {
      this.compiledTemplate = compileView(this.template);
    }

    return this.compiledTemplate(await this.model(query));
  }
};

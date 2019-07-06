module.exports = {
  async render(controller, ...args) {
    const template = document.createElement('template');
    template.innerHTML = await controller.render(...args);
    return template.content;
  }
};

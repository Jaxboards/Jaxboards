module.exports = {
  async render(controller) {
    const template = document.createElement('template');
    template.innerHTML = await controller.render();
    return template.content;
  }
};

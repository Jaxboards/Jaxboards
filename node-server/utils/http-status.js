class BadRequest extends Error {
  constructor() {
    super(...arguments);
    this.status = 400;
  }
}

module.exports = {
  BadRequest
};

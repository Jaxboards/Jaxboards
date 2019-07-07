class BadRequest extends Error {
  constructor(message = 'Bad Request', ...args) {
    super(message, ...args);
    this.status = 400;
  }
}

module.exports = {
  BadRequest
};

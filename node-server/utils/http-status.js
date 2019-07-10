class BadRequest extends Error {
  constructor(message = 'Bad Request', ...args) {
    super(message, ...args);
    this.status = 400;
  }
}

class ForbiddenRequest extends Error {
  constructor(message = 'Forbidden', ...args) {
    super(message, ...args);
    this.status = 401;
  }
}

class UnauthorizedRequest extends Error {
  constructor(message = 'Unauthorized', ...args) {
    super(message, ...args);
    this.status = 403;
  }
}

module.exports = {
  BadRequest,
  ForbiddenRequest,
  UnauthorizedRequest
};

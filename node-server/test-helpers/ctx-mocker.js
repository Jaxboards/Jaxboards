module.exports = function mockCTX(properties = {}) {
  const passportExtensions = {
    isAuthenticated: () => !!properties.user,
    state: {
      user: properties.user
    }
  };
  return Object.assign(
    {
      query: {},
      params: {},
      request: {},
      redirect: () => {}
    },
    properties,
    passportExtensions
  );
};

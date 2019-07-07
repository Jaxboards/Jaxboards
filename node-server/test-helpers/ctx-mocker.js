module.exports = function mockCTX(properties) {
  return Object.assign(
    {
      query: {},
      params: {},
      request: {},
      redirect: () => {}
    },
    properties
  );
};

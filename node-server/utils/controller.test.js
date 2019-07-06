const Controller = require('../utils/controller');
const { render } = require('../test-helpers/controller-helpers');

test('It renders nested controllers and passes context to all', async () => {
  expect.assertions(4);

  const mockCTX = {};

  class TestController extends Controller {
    constructor() {
      super();
      this.compiledTemplate = () => '';
    }
  }

  class ChildA extends TestController {
    async render(ctx, children) {
      await super.render(...arguments);
      expect(ctx).toBe(mockCTX);
      expect(children.length).toBe(1);
      return '';
    }
  }
  class ChildB extends TestController {
    async render(ctx, children) {
      await super.render(...arguments);

      expect(ctx).toBe(mockCTX);
      expect(children.length).toBe(0);
      return '';
    }
  }

  const childControllers = [new ChildA(), new ChildB()];
  await render(new TestController(), mockCTX, childControllers);
});

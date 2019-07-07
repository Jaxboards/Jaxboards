const Controller = require('../utils/controller');
const { render } = require('../test-helpers/controller-helpers');
const injectionMocker = require('../test-helpers/injection-mocker');
const mockCTX = require('../test-helpers/ctx-mocker');

test('It renders nested controllers and passes context to all', async () => {
  expect.assertions(4);

  const mockedCTX = mockCTX();

  const inject = injectionMocker({
    'views/mock-template': () => ''
  });

  class TestController extends Controller {
    static get template() {
      return 'mock-template';
    }
  }

  class ChildA extends TestController {
    async render(ctx, children) {
      await super.render(...arguments);
      expect(ctx).toBe(mockedCTX);
      expect(children.length).toBe(1);
      return '';
    }
  }
  class ChildB extends TestController {
    async render(ctx, children) {
      await super.render(...arguments);

      expect(ctx).toBe(mockedCTX);
      expect(children.length).toBe(0);
      return '';
    }
  }

  const childControllers = [new ChildA(inject), new ChildB(inject)];
  await render(new TestController(inject), mockedCTX, childControllers);
});

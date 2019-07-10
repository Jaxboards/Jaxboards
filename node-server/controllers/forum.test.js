const SequelizeMock = require('sequelize-mock');
const ForumController = require('./forum');
const injectionMocker = require('../test-helpers/injection-mocker');
const mockCtx = require('../test-helpers/ctx-mocker');

const DBConnectionMock = new SequelizeMock();

const ForumModelMock = DBConnectionMock.define(
  'forum',
  {
    redirect: 'http://example.com',
    redirects: 5
  },
  {
    instanceMethods: {
      // sequelize-mock doesn't stub increment, bleh
      increment(property) {
        this[property] += 1;
      }
    }
  }
);

test('Redirect forums work as expected', async () => {
  const forumId = 5;

  const mockForumInstance = await ForumModelMock.findOne();
  const inject = injectionMocker({
    'resources/forums': {
      find: () => mockForumInstance
    },
    'resources/topics': {
      findAndCountAll: () => ({})
    }
  });

  const indexController = new ForumController(inject);
  let redirectedUrl;
  await indexController.model(
    mockCtx({
      params: { id: forumId },
      redirect(url) {
        redirectedUrl = url;
      }
    })
  );

  expect(mockForumInstance.redirects).toBe(6);
  expect(redirectedUrl).toBe('http://example.com');
});

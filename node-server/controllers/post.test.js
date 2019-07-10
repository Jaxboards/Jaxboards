const PostController = require('./post');
const injectionMocker = require('../test-helpers/injection-mocker');
const mockCTX = require('../test-helpers/ctx-mocker');
const { BadRequest, UnauthorizedRequest } = require('../utils/http-status');

test('It creates posts for topics', async () => {
  const topicId = 5;

  const mockCreate = jest.fn(() => Promise.resolve());
  const mockRouterUrl = jest.fn(() => 'router generated URL');

  const inject = injectionMocker({
    'resources/topics': {
      find: () => ({ id: topicId })
    },
    'resources/posts': {
      create: mockCreate
    },
    router: {
      url: mockRouterUrl
    }
  });

  const indexController = new PostController(inject);
  const mockRedirect = jest.fn();
  await indexController.model(
    mockCTX({
      isAuthenticated: () => true,
      query: { tid: topicId },
      request: {
        body: {
          postdata: 'Post body!'
        }
      },
      redirect: mockRedirect
    })
  );

  // Post is created
  expect(mockCreate).toHaveBeenCalledWith(
    expect.objectContaining({
      post: 'Post body!',
      tid: topicId
    })
  );

  // Redirection happens
  expect(mockRedirect).toHaveBeenCalledWith('router generated URL');
  expect(mockRouterUrl).toHaveBeenCalledWith('topic', topicId);
});

test('It throws errors for missing request parameters', async () => {
  const topicId = 5;

  const mockCreate = jest.fn(() => Promise.resolve());
  const mockRouterUrl = jest.fn(() => 'router generated URL');

  const inject = injectionMocker({
    'resources/topics': {
      find: () => ({ id: topicId })
    },
    'resources/posts': {
      create: mockCreate
    },
    router: {
      url: mockRouterUrl
    }
  });

  const indexController = new PostController(inject);

  expect(
    indexController.model(
      mockCTX({
        isAuthenticated: () => true,
        request: {
          body: {
            postdata: 'Just a post body!'
          }
        }
      })
    )
  ).rejects.toThrow(BadRequest);

  expect(mockCreate).not.toHaveBeenCalled();
});

test('It does not allow guests to post', async () => {
  const topicId = 5;

  const mockCreate = jest.fn(() => Promise.resolve());
  const mockRouterUrl = jest.fn(() => 'router generated URL');

  const inject = injectionMocker({
    'resources/topics': {
      find: () => ({ id: topicId })
    },
    'resources/posts': {
      create: mockCreate
    },
    router: {
      url: mockRouterUrl
    }
  });

  const indexController = new PostController(inject);

  expect(
    indexController.model(
      mockCTX({
        isAuthenticated: () => false,
        request: {
          body: {
            postdata: 'Just a post body!'
          }
        }
      })
    )
  ).rejects.toThrow(UnauthorizedRequest);

  expect(mockCreate).not.toHaveBeenCalled();
});

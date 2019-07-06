const SequelizeMock = require('sequelize-mock');
const TopicController = require('./topic');
const injectionMocker = require('../test-helpers/injection-mocker');

const DBConnectionMock = new SequelizeMock();

const TopicModelMock = DBConnectionMock.define(
  'topic',
  {
    views: 5
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

test('It counts views', async () => {
  const topicId = 5;

  const mockTopicModelInstance = await TopicModelMock.findOne();
  const inject = injectionMocker({
    'resources/topics': {
      find: () => mockTopicModelInstance
    },
    'resources/posts': {
      findAndCountAll: () => ({})
    }
  });

  const indexController = new TopicController(inject);
  await indexController.model({
    query: {},
    params: { id: topicId }
  });

  expect(mockTopicModelInstance.views).toBe(6);
});

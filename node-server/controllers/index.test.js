const IndexController = require('./index');
const injectionMocker = require('../test-helpers/injection-mocker');
const { render } = require('../test-helpers/controller-helpers');

// TODO: move this to test environment setup file.
// Ref: https://jestjs.io/docs/en/configuration.html#setupfilesafterenv-array
require('jest-dom/extend-expect');

test('It renders', async () => {
  const forumId = 5;

  const inject = injectionMocker({
    'resources/forums': { findAll() {} },
    'resources/member_groups': { findAll() {} },
    'resources/categories': {
      findAll() {
        // TODO: Create a mock helper to use sequelize to mock models rather than using pure JSON
        return [
          {
            id: 2,
            title: 'category b',
            forums: [
              {
                id: forumId,
                lp_date: '2019-07-01T18:39:48.000Z',
                subtitle: 'Forum Description',
                title: 'Forum Title',
                topics: 200,
                last_topic: {
                  id: 1,
                  title: 'Last topic title',
                  replies: 300
                },
                last_poster: {
                  id: 1,
                  name: 'Last poster',
                  group_id: 2,
                  display_name: 'Last poster name'
                }
              }
            ]
          }
        ];
      }
    },
    'resources/stats': { findAll() {} }
  });

  const indexController = new IndexController(inject);
  const dom = await render(indexController);

  expect(dom.querySelector('.box.collapse-box .title')).toHaveTextContent(
    'category b'
  );
  expect(
    dom.querySelector('.box.collapse-box .boardindex .forum a')
  ).toHaveTextContent('Forum Title');
  expect(
    dom.querySelector('.box.collapse-box .boardindex .forum .description')
  ).toHaveTextContent('Forum Description');
  expect(dom.querySelector(`#fid_${forumId}_lastpost a`)).toHaveTextContent(
    'Last topic title'
  );
  expect(
    dom.querySelector(`#fid_${forumId}_lastpost .user1.mgroup2`)
  ).toHaveTextContent('Last poster name');
  expect(dom.querySelector(`#fid_${forumId}_topics`)).toHaveTextContent('200');
  expect(dom.querySelector(`#fid_${forumId}_replies`)).toHaveTextContent('300');
});

const IndexController = require('./index');

// TODO: move this to test environment setup file.
// Ref: https://jestjs.io/docs/en/configuration.html#setupfilesafterenv-array
require('jest-dom/extend-expect');

// const CategoryModel = require('../models/category');
const { inject: realInject } = require('../injections');

class InjectionMocker {
  constructor(injections = {}) {
    this.injections = injections;
    this.inject = path => this.injections[path] || realInject(path);
  }

  register(path, injection) {
    this.injections[path] = injection;
  }
}

test('It renders', async () => {
  const { inject } = new InjectionMocker({
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
                id: 2,
                lp_date: '2019-07-01T18:39:48.000Z',
                lp_topic: 'Test',
                mods: '10',
                subtitle: 'Forum Description',
                title: 'Forum Title',
                topics: 3,
                last_topic: {
                  title: 'Test',
                  subtitle: '',
                  lp_uid: 1,
                  lp_date: '2019-07-01T18:39:48.000Z',
                  fid: 3,
                  auth_id: 1,
                  replies: 0,
                  views: 1,
                  summary: 'test',
                  locked: false,
                  date: '2019-07-01T18:39:48.000Z',
                  op: 14,
                  cal_event: 0
                },
                last_poster: {
                  id: 1,
                  name: 'sean',
                  group_id: 2,
                  display_name: 'Test'
                }
              }
            ]
          }
        ];
      }
    },
    'resources/stats': { findAll() {} }
  });

  const injectionController = new IndexController(inject);

  // TODO: pull a helper for converting DOM from HBS
  const template = document.createElement('template');
  template.innerHTML = await injectionController.render();
  const dom = template.content;

  expect(dom.querySelector('.box.collapse-box .title')).toHaveTextContent(
    'category b'
  );
  expect(
    dom.querySelector('.box.collapse-box .boardindex .forum a')
  ).toHaveTextContent('Forum Title');
  expect(
    dom.querySelector('.box.collapse-box .boardindex .forum .description')
  ).toHaveTextContent('Forum Description');
});

const SequelizeMock = require('sequelize-mock');
const ApplicationController = require('./application');
const injectionMocker = require('../test-helpers/injection-mocker');
const mockCtx = require('../test-helpers/ctx-mocker');
const { render } = require('../test-helpers/controller-helpers');

const DBConnectionMock = new SequelizeMock();

const MemberModelMock = DBConnectionMock.define('member', {
  id: 5,
  display_name: 'Sean',
  last_visit: '2018-09-14T02:45:59.000Z'
});

test('Displays user box when user is logged in', async () => {
  const user = await MemberModelMock.findOne();

  const applicationController = new ApplicationController(injectionMocker());

  const dom = await render(applicationController, mockCtx({ user }));

  expect(dom.querySelector('#userbox form')).not.toBeTruthy();
  expect(dom.querySelector('#userbox .welcome')).toHaveTextContent('Sean');
  expect(dom.querySelector('#userbox .lastvisit')).toHaveTextContent(
    'Last Visit: Sep 13th, 2018 @ 10:45pm'
  );
});

test('Displays login form when user is logged out', async () => {
  const applicationController = new ApplicationController(injectionMocker());

  const dom = await render(applicationController, mockCtx());

  expect(dom.querySelector('#userbox form')).toBeTruthy();
  expect(dom.querySelector('#userbox input[name=username]')).toBeTruthy();
  expect(dom.querySelector('#userbox input[name=password]')).toBeTruthy();
});

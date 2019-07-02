const ForumsResource = require('../resources/forums');
const CategoriesResource = require('../resources/categories');
const StatsResource = require('../resources/stats');
const MemberGroupsResource = require('../resources/member_groups');
const Controller = require('./controller');

class IndexController extends Controller {
  // eslint-disable-next-line class-methods-use-this
  get template() {
    return 'index';
  }

  // eslint-disable-next-line class-methods-use-this
  async model() {
    return {
      themePath: '/Themes/Default/css.css',
      forums: await ForumsResource.findAll(),
      categories: await CategoriesResource.findAll({
        full: true
      }),
      stats: await StatsResource.findAll(),
      memberGroups: await MemberGroupsResource.findAll({
        legend: true
      })
    };
  }

  // eslint-disable-next-line class-methods-use-this
  async afterModel(model) {
    return model;
  }
}

module.exports = new IndexController();

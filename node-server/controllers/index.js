const Controller = require('./controller');

class IndexController extends Controller {
  constructor(inject) {
    super(...arguments);
    this.ForumsResource = inject('resources/forums');
    this.MemberGroupsResource = inject('resources/member_groups');
    this.CategoriesResource = inject('resources/categories');
    this.StatsResource = inject('resources/stats');
  }

  // eslint-disable-next-line class-methods-use-this
  get template() {
    return 'index';
  }

  // eslint-disable-next-line class-methods-use-this
  async model() {
    return {
      themePath: '/Themes/Default/css.css',
      forums: await this.ForumsResource.findAll(),
      categories: await this.CategoriesResource.findAll({
        full: true
      }),
      stats: await this.StatsResource.findAll(),
      memberGroups: await this.MemberGroupsResource.findAll({
        legend: true
      })
    };
  }

  // eslint-disable-next-line class-methods-use-this
  async afterModel(model) {
    return model;
  }
}

module.exports = IndexController;

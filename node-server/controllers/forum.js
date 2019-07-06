const Controller = require('../utils/controller');
const { NUM_TOPICS_PER_PAGE } = require('../utils/constants');

class ForumController extends Controller {
  constructor(inject) {
    super(...arguments);
    this.ForumsResource = inject('resources/forums');
    this.MemberGroupsResource = inject('resources/member_groups');
    this.TopicsResource = inject('resources/topics');
  }

  // eslint-disable-next-line class-methods-use-this
  get template() {
    return 'forum';
  }

  // eslint-disable-next-line class-methods-use-this
  async model(ctx) {
    const forumId = ctx.params.id;
    let page = parseInt(ctx.query.page, 10);
    page = Number.isNaN(page) ? 0 : page;

    const { count, rows: topics } = await this.TopicsResource.findAndCountAll({
      fid: forumId,
      page
    });

    return {
      forum: await this.ForumsResource.find(forumId),
      subforums: await this.ForumsResource.findAll({ path: forumId }),
      topics,
      page,
      totalPages: Math.ceil(count / NUM_TOPICS_PER_PAGE)
    };
  }
}

module.exports = ForumController;

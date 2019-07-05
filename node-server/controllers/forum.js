const Controller = require('./controller');

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
    const page = ctx.query.page || 0;

    return {
      forum: await this.ForumsResource.find(forumId),
      subforums: await this.ForumsResource.findAll({ path: forumId }),
      topics: await this.TopicsResource.findAll({
        fid: forumId,
        page
      })
    };
  }
}

module.exports = ForumController;

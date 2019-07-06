const Controller = require('./controller');
const { NUM_POSTS_PER_PAGE } = require('../utils/constants');

class TopicController extends Controller {
  constructor(inject) {
    super(...arguments);
    this.PostsResource = inject('resources/posts');
    this.TopicsResource = inject('resources/topics');
  }

  // eslint-disable-next-line class-methods-use-this
  get template() {
    return 'topic';
  }

  // eslint-disable-next-line class-methods-use-this
  async model(ctx) {
    const topicId = ctx.params.id;
    let page = parseInt(ctx.query.page, 10);
    page = Number.isNaN(page) ? 0 : page;

    const { count, rows: posts } = await this.PostsResource.findAndCountAll({
      tid: topicId,
      page
    });

    return {
      topic: await this.TopicsResource.find(topicId),
      posts,
      count,
      page,
      totalPages: Math.ceil(count / NUM_POSTS_PER_PAGE)
    };
  }
}

module.exports = TopicController;

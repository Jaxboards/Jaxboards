const Controller = require('./controller');

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
    const page = ctx.query.page || 0;

    return {
      topic: await this.TopicsResource.find(topicId),
      posts: await this.PostsResource.findAll({
        tid: topicId,
        page
      })
    };
  }
}

module.exports = TopicController;

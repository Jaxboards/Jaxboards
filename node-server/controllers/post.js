const Controller = require('../utils/controller');
const { BadRequest, UnauthorizedRequest } = require('../utils/http-status');

class PostController extends Controller {
  constructor(inject) {
    super(...arguments);
    this.ForumsResource = inject('resources/forums');
    this.TopicsResource = inject('resources/topics');
    this.PostsResource = inject('resources/posts');
    this.router = inject('router');
  }

  static get template() {
    return 'post';
  }

  async model(ctx) {
    const { tid, fid } = ctx.query;

    const model = {};

    if (tid) {
      model.topic = await this.TopicsResource.find(tid);
    } else if (fid) {
      model.forum = await this.ForumsResource.find(fid);
    }

    if (Object.keys(ctx.request.body).length) {
      await this.handlePost(ctx);
    }

    return model;
  }

  handlePost(ctx) {
    const { tid, fid } = ctx.query;
    const { postdata } = ctx.request.body;
    if (!ctx.isAuthenticated()) {
      throw new UnauthorizedRequest('This forum does not allow guest posts');
    }
    if (tid) {
      // create the post and redirect back to topic view
      return this.PostsResource.create({
        tid,
        post: postdata
      }).then(() => ctx.redirect(this.router.url('topic', tid)));
    }
    if (fid) {
      throw new Error('Topic creation not implemented');
    }
    throw new BadRequest();
  }
}

module.exports = PostController;

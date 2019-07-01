const BaseResource = require('./resource');

class MembersResource extends BaseResource {
  batchGet(ids) {
    const { sequelize } = this;
    const [members] = this.prefixTableNames('members');

    // TODO: Sanitize ids or figure out how to construct batch get query with sequelize
    return sequelize.query(
      `SELECT \`id\`,\`display_name\`,\`group_id\` FROM \`${members}\` WHERE id IN ($ids)`,
      {
        bind: {
          ids
        },
        type: sequelize.QueryTypes.SELECT
      },
    );
  }

  addRoutes(router) {
    router.get('/members', async ctx => {
      if (ctx.query.ids) {
        ctx.body = await this.batchGet(ctx.query.ids);
      }
    });
  }
}

module.exports = MembersResource;

const BaseResource = require('./resource');

class MemberGroupsResource extends BaseResource {

  findAll(searchQuery = {}) {
    const { sequelize } = this;
    const [member_groups] = sequelize.prefixTableNames(['member_groups']);

    if (searchQuery.legend) {
      return sequelize.query(`
        SELECT \`id\`,\`title\` FROM ${member_groups} WHERE \`legend\`=1 ORDER BY \`title\`
        `,
        { type: sequelize.QueryTypes.SELECT }
      );
    }
  }

  addRoutes(router) {
    router.get('/member_groups', async ctx => {
      if (ctx.query.legend) {
        ctx.body = await this.findAll({
          legend: true
        });
      }
    });
  }
}

module.exports = MemberGroupsResource;

const BaseResource = require('./resource');

class ForumResource extends BaseResource {
  getAll() {
    const { sequelize } = this;
    const [forums, members] = this.prefixTableNames('forums', 'members');

    return sequelize.query(`
      SELECT f.\`id\` AS \`id\`,
        f.\`cat_id\` AS \`cat_id\`,f.\`title\` AS \`title\`,f.\`subtitle\` AS \`subtitle\`,
        f.\`lp_uid\` AS \`lp_uid\`,UNIX_TIMESTAMP(f.\`lp_date\`) AS \`lp_date\`,
        f.\`lp_tid\` AS \`lp_tid\`,f.\`lp_topic\` AS \`lp_topic\`,f.\`path\` AS \`path\`,
        f.\`show_sub\` AS \`show_sub\`,f.\`redirect\` AS \`redirect\`,
        f.\`topics\` AS \`topics\`,f.\`posts\` AS \`posts\`,f.\`order\` AS \`order\`,
        f.\`perms\` AS \`perms\`,f.\`orderby\` AS \`orderby\`,f.\`nocount\` AS \`nocount\`,
        f.\`redirects\` AS \`redirects\`,f.\`trashcan\` AS \`trashcan\`,f.\`mods\` AS \`mods\`,
        f.\`show_ledby\` AS \`show_ledby\`,m.\`display_name\` AS \`lp_name\`,
        m.\`group_id\` AS \`lp_gid\`
      FROM ${forums} f
      LEFT JOIN ${members} m
          ON f.\`lp_uid\`=m.\`id\`
      ORDER BY f.\`order\`, f.\`title\` ASC
    `,
    { type: sequelize.QueryTypes.SELECT }
    );
  }

  findAll(searchQuery = {}) {
    const { sequelize } = this;
    const [forums, members] = this.prefixTableNames('forums', 'members');

    if (searchQuery.lp_date) {
      return sequelize.query(`
        SELECT
          f.\`id\` AS \`id\`,
          f.\`lp_tid\` AS \`lp_tid\`,
          f.\`lp_topic\` AS \`lp_topic\`,
          UNIX_TIMESTAMP(f.\`lp_date\`) AS \`lp_date\`,
          f.\`lp_uid\` AS \`lp_uid\`,
          f.\`topics\` AS \`topics\`,
          f.\`posts\` AS \`posts\`,
          m.\`display_name\` AS \`lp_name\`,
          m.\`group_id\` AS \`lp_gid\`
        FROM ${forums} f
        LEFT JOIN ${members} m
            ON f.\`lp_uid\`=m.\`id\`
        WHERE f.\`lp_date\`>=$lp_date
        `,
        {
          type: sequelize.QueryTypes.SELECT,
          bind: {
            lp_date: searchQuery.lp_date
          }
        }
      );
    }
  }

  addRoutes(router) {
    router.get('/forums', async ctx => {
      if (ctx.query.lp_date) {
        ctx.body = await this.findAll(ctx.query);
      } else {
        ctx.body = await this.getAll();
      }
    });
  }
}

module.exports = ForumResource;

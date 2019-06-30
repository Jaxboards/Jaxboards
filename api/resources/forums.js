const BaseResource = require('./resource');

class ForumResource extends BaseResource {
  getAll() {
    const { sequelize } = this;
    const [forums, members] = sequelize.prefixTableNames(['forums', 'members']);

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
  `, { type: sequelize.QueryTypes.SELECT });
  }

  addRoutes(router) {
    const self = this;
    router.get('/forums', async ctx => {
      ctx.body = JSON.stringify(await self.getAll());
    });
  }
}

module.exports = ForumResource;

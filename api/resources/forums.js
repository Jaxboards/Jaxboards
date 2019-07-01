const BaseResource = require('./resource');

class ForumResource extends BaseResource {
  find(id) {
    const { sequelize } = this;
    const [forums, categories] = this.prefixTableNames('forums', 'categories');

    return sequelize.query(`
        SELECT
          f.\`id\` AS \`id\`,
          f.\`cat_id\` AS \`cat_id\`,
          f.\`title\` AS \`title\`,
          f.\`subtitle\` AS \`subtitle\`,
          f.\`lp_uid\` AS \`lp_uid\`,
          UNIX_TIMESTAMP(f.\`lp_date\`) AS \`lp_date\`,
          f.\`lp_tid\` AS \`lp_tid\`,
          f.\`lp_topic\` AS \`lp_topic\`,
          f.\`path\` AS \`path\`,
          f.\`show_sub\` AS \`show_sub\`,
          f.\`redirect\` AS \`redirect\`,
          f.\`topics\` AS \`topics\`,
          f.\`posts\` AS \`posts\`,
          f.\`order\` AS \`order\`,
          f.\`perms\` AS \`perms\`,
          f.\`orderby\` AS \`orderby\`,
          f.\`nocount\` AS \`nocount\`,
          f.\`redirects\` AS \`redirects\`,
          f.\`trashcan\` AS \`trashcan\`,
          f.\`mods\` AS \`mods\`,
          f.\`show_ledby\` AS \`show_ledby\`,
          c.\`title\` AS \`cat\`
        FROM ${forums} f
        LEFT JOIN ${categories} c
            ON f.\`cat_id\`=c.\`id\`
        WHERE f.\`id\`=$id LIMIT 1`,
        {
          type: sequelize.QueryTypes.SELECT,
          bind: {
            id
          }
        }
      );
  }

  findAll(query = {}) {
    const { sequelize } = this;
    const [forums, members] = this.prefixTableNames('forums', 'members');

    // TODO: Don't build our own queries
    let where = '';
    if (query.lp_date) {
      where = `WHERE f.\`lp_date\`>=$lp_date`;
    } else if (query.path) {
      where = `WHERE f.\`path\`=$path OR f.\`path\` LIKE $path_like`;
    }

    return sequelize.query(`
        SELECT
          f.\`id\` AS \`id\`,
          f.\`cat_id\` AS \`cat_id\`,
          f.\`title\` AS \`title\`,
          f.\`subtitle\` AS \`subtitle\`,
          f.\`lp_uid\` AS \`lp_uid\`,
          UNIX_TIMESTAMP(f.\`lp_date\`) AS \`lp_date\`,
          f.\`lp_tid\` AS \`lp_tid\`,
          f.\`lp_topic\` AS \`lp_topic\`,
          f.\`path\` AS \`path\`,
          f.\`show_sub\` AS \`show_sub\`,
          f.\`redirect\` AS \`redirect\`,
          f.\`topics\` AS \`topics\`,
          f.\`posts\` AS \`posts\`,
          f.\`order\` AS \`order\`,
          f.\`perms\` AS \`perms\`,
          f.\`orderby\` AS \`orderby\`,
          f.\`nocount\` AS \`nocount\`,
          f.\`redirects\` AS \`redirects\`,
          f.\`trashcan\` AS \`trashcan\`,
          f.\`mods\` AS \`mods\`,
          f.\`show_ledby\` AS \`show_ledby\`,
          m.\`display_name\` AS \`lp_name\`,
          m.\`group_id\` AS \`lp_gid\`
        FROM ${forums} f
        LEFT JOIN ${members} m
            ON f.\`lp_uid\`=m.\`id\`
        ${where}
        ORDER BY f.\`order\`, f.\`title\` ASC
      `,
      {
        type: sequelize.QueryTypes.SELECT,
        bind: {
          lp_date: query.lp_date,
          path: query.path,
          path_like: query.path ? `% ${query.path}` : ''
        }
      }
    );
  }

  addRoutes(router) {
    router.get('/forums', async ctx => {
      ctx.body = await this.findAll(ctx.query);
    });

    router.get('/forum/:id', async ctx => {
      ctx.body = await this.find(ctx.params.id);
    })
  }
}

module.exports = ForumResource;

const BaseResource = require('./resource');

class SessionResource extends BaseResource {
  findAll() {
    const { sequelize } = this;
    const [session, members] = this.prefixTableNames('session', 'members');

    return sequelize.query(`
      SELECT
        UNIX_TIMESTAMP(MAX(s.\`last_update\`)) AS \`last_update\`,
        ANY_VALUE(m.\`id\`) AS \`id\`,
        ANY_VALUE(m.\`group_id\`) AS \`group_id\`,
        ANY_VALUE(m.\`display_name\`) AS \`name\`,
        CONCAT(MONTH(m.\`birthdate\`),' ',DAY(m.\`birthdate\`)) AS \`birthday\`,
        ANY_VALUE(s.\`hide\`) AS \`hide\`,
        UNIX_TIMESTAMP(ANY_VALUE(s.\`read_date\`)) AS \`read_date\`
      FROM ${session} s
      LEFT JOIN ${members} m ON s.\`uid\`=m.\`id\`
      WHERE s.\`uid\`
      GROUP BY s.\`uid\`, m.\`id\`
      ORDER BY \`name\`, \`last_update\` DESC
      `,
      { type: sequelize.QueryTypes.SELECT });
  }

  addRoutes(router) {
    router.get('/sessions', async ctx => {
      ctx.body = await this.findAll();
    });
  }
}

module.exports = SessionResource;

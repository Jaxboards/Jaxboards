const BaseResource = require('./resource');

class StatsResource extends BaseResource {
  findAll() {
    const { sequelize } = this;
    const [stats, members] = this.prefixTableNames('stats', 'members');

    return sequelize.query(
      `SELECT s.\`posts\` AS \`posts\`,s.\`topics\` AS \`topics\`,s.\`members\` AS \`members\`,
        s.\`most_members\` AS \`most_members\`,
        s.\`most_members_day\` AS \`most_members_day\`,
        s.\`last_register\` AS \`last_register\`,m.\`group_id\` AS \`group_id\`,
        m.\`display_name\` AS \`display_name\`
      FROM ${stats} s
      LEFT JOIN ${members} m
      ON s.\`last_register\`=m.\`id\``,
      { type: sequelize.QueryTypes.SELECT }
    );
  }
}

module.exports = new StatsResource();

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
      }
    );
  }

  find(id) {
    const { sequelize } = this;
    const [members] = this.prefixTableNames('members');

    return sequelize.query(
      /**
       * TODO: Private fields:
        \`pass\`,
        \`email\`,
        INET6_NTOA(\`ip\`) AS \`ip\`,
        \`ucpnotepad\`,
       */

      `SELECT
        \`id\`,
        \`name\`,
        \`sig\`,
        \`posts\`,
        \`group_id\`,
        \`avatar\`,
        \`usertitle\`,
        UNIX_TIMESTAMP(\`join_date\`) AS \`join_date\`,
        UNIX_TIMESTAMP(\`last_visit\`) AS \`last_visit\`,
        \`contact_skype\`,
        \`contact_yim\`,
        \`contact_msn\`,
        \`contact_gtalk\`,
        \`contact_aim\`,
        \`contact_twitter\`,
        \`website\`,
        \`birthdate\`,
        DAY(\`birthdate\`) AS \`dob_day\`,
        MONTH(\`birthdate\`) AS \`dob_month\`,
        YEAR(\`birthdate\`) AS \`dob_year\`,
        \`about\`,
        \`display_name\`,
        \`full_name\`,
        \`contact_steam\`,
        \`location\`,
        \`gender\`,
        \`friends\`,
        \`enemies\`,
        \`sound_shout\`,
        \`sound_im\`,
        \`sound_pm\`,
        \`sound_postinmytopic\`,
        \`sound_postinsubscribedtopic\`,
        \`notify_pm\`,
        \`notify_postinmytopic\`,
        \`notify_postinsubscribedtopic\`,
        \`skin_id\`,
        \`email_settings\`,
        \`nowordfilter\`,
        \`mod\`,
        \`wysiwyg\`

      FROM ${members}
      WHERE id=$id`,
      {
        type: sequelize.QueryTypes.SELECT,
        bind: {
          id
        }
      }
    );
  }

  addRoutes(router) {
    router.get('/members', async ctx => {
      if (ctx.query.ids) {
        ctx.body = await this.batchGet(ctx.query.ids);
      }
    });
    router.get('/member/:id', async ctx => {
      ctx.body = await this.find(ctx.params.id);
    });
  }
}

module.exports = MembersResource;

const Sequelize = require('sequelize');

class MemberGroupsModel extends Sequelize.Model {}

module.exports = {
  init(sequelize) {
    MemberGroupsModel.init(
      {
        title: Sequelize.STRING,
        can_post: Sequelize.BOOLEAN,
        can_edit_posts: Sequelize.BOOLEAN,
        can_post_topics: Sequelize.BOOLEAN,
        can_edit_topics: Sequelize.BOOLEAN,
        can_add_comments: Sequelize.BOOLEAN,
        can_delete_comments: Sequelize.BOOLEAN,
        can_view_board: Sequelize.BOOLEAN,
        can_view_offline_board: Sequelize.BOOLEAN,
        flood_control: Sequelize.INTEGER,
        can_override_locked_topics: Sequelize.BOOLEAN,
        icon: Sequelize.STRING,
        can_shout: Sequelize.BOOLEAN,
        can_moderate: Sequelize.BOOLEAN,
        can_delete_shouts: Sequelize.BOOLEAN,
        can_delete_own_shouts: Sequelize.BOOLEAN,
        can_karma: Sequelize.BOOLEAN,
        can_im: Sequelize.BOOLEAN,
        can_pm: Sequelize.BOOLEAN,
        can_lock_own_topics: Sequelize.BOOLEAN,
        can_delete_own_topics: Sequelize.BOOLEAN,
        can_use_sigs: Sequelize.BOOLEAN,
        can_attach: Sequelize.BOOLEAN,
        can_delete_own_posts: Sequelize.BOOLEAN,
        can_poll: Sequelize.BOOLEAN,
        can_access_acp: Sequelize.BOOLEAN,
        can_view_shoutbox: Sequelize.BOOLEAN,
        can_view_stats: Sequelize.BOOLEAN,
        legend: Sequelize.BOOLEAN,
        can_view_fullprofile: {
          type: Sequelize.BOOLEAN,
          defaultValue: true
        }
      },
      {
        modelName: 'member_group',
        sequelize,
        timestamps: false
      }
    );
  },

  model: MemberGroupsModel
};

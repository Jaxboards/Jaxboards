/* global RUN */
import { assign, onDOMReady } from './JAX/util';
import Event from './JAX/event';

// TODO: Find a place for this state
let onPageChangeOld;

class ModControls {
  checklocation() {
    const { whichone } = this;
    const regex = whichone ? /act=vt(\d+)/ : /act=vf(\d+)/;
    if (document.location.toString().match(regex)) {
      this.moveto(RegExp.$1);
    } else {
      RUN.stream.commands.modcontrols_move();
    }
  }

  moveto(id) {
    const { whichone } = this;
    this.getitup(
      `<form method="post" data-ajax-form="true">move ${
        whichone ? 'posts' : 'topics'
      } here? <input type="hidden" name="act" value="modcontrols" />`
        + `<input type="hidden" name="${
          whichone ? 'dop' : 'dot'
        }" value="moveto" /><input type="hidden" name="id" value="${id}" /><input type="submit" value="Yes" />`
        + '<input type="submit" name="cancel" value="Cancel" '
        + 'onclick="this.form.submitButton=this" /></form>',
    );
  }

  getitup(html) {
    let modb = this.modb || document.querySelector('#modbox');
    if (!this.modb) {
      modb = document.createElement('div');
      modb.id = 'modbox';
      document.body.appendChild(modb);
    }
    modb.style.display = 'block';
    modb.innerHTML = html;
    this.modb = modb;
  }

  takeitdown() {
    if (onPageChangeOld) {
      Event.onPageChange = onPageChangeOld;
      onPageChangeOld = null;
    } else Event.onPageChange = null;
    if (this.modb) {
      this.modb.innerHTML = '';
      this.modb.style.display = 'none';
    }
  }

  // eslint-disable-next-line class-methods-use-this
  togbutton(button) {
    button.classList.toggle('selected');
  }
}

onDOMReady(() => {
  RUN.modcontrols = new ModControls();
});

onDOMReady(() => {
  assign(RUN.stream.commands, {
    modcontrols_getitup(html) {
      this.busy = true;
      RUN.modcontrols.getitup(html);
    },

    modcontrols_postsync(a) {
      let pids = [];
      if (a[0] && (typeof a[0] === 'string' || typeof a[0] === 'number')) {
        pids = `${a[0]}`.split(',');
      }
      const pl = pids ? pids.length : 0;
      let tids = [];
      if (a[1] && (typeof a[1] === 'string' || typeof a[1] === 'number')) {
        tids = `${a[1]}`.split(',');
      }
      const tl = tids ? tids.length : 0;
      const html = `${"<form method='post' data-ajax-form='true'>"
          + "<input type='hidden' name='act' value='modcontrols' />"}${
        tl
          ? `${"<select name='dot'>"
                + "<option value='delete'>Delete</option>"
                + "<option value='merge'>Merge</option>"
                + "<option value='move'>Move</option>"
                + "<option value='pin'>Pin</option>"
                + "<option value='unpin'>Unpin</option>"
                + "<option value='lock'>Lock</option>"
                + "<option value='unlock'>Unlock</option>"
                + '</select>'
                + '&nbsp; &nbsp; <strong>'}${tl}</strong> topic${
            tl > 1 ? 's' : ''
          }${pl ? ' and <br />' : ''}`
          : ''
      }${
        pl
          ? `${"<select name='dop'>"
                + "<option value='delete'>Delete</option>"
                + "<option value='move'>Move</option>"
                + '</select> &nbsp; &nbsp; <strong>'}${pl}</strong> post${
            pids.length > 1 ? 's' : ''
          }`
          : ''
      }${
        pl && tl ? '<br />' : ' &nbsp; &nbsp; '
      }<input type='submit' value='Go' /> `
        + "<input name='cancel' type='submit' "
        + "onclick='this.form.submitButton=this;' value='Cancel' /></form>";
      assign(RUN.modcontrols, {
        tids,
        tidl: tl,
        pids,
        pidl: pl,
      });
      if (tl || pl) RUN.modcontrols.getitup(html);
      else RUN.modcontrols.takeitdown();
    },

    modcontrols_move(a) {
      const whichone = parseInt(
        a && a[0] ? a[0] : RUN.modcontrols.whichone,
        10,
      );
      if (!this.busy && onPageChangeOld) {
        onPageChangeOld = Event.onPageChange;
      }
      RUN.modcontrols.whichone = whichone;
      Event.onPageChange = RUN.modcontrols.checklocation;
      RUN.modcontrols.getitup(
        `Ok, now browse to the ${
          whichone ? 'topic' : 'forum'
        } you want to move the ${
          whichone
            ? `${RUN.modcontrols.pidl} posts`
            : `${RUN.modcontrols.tidl} topics`
        } to...`,
      );
    },

    modcontrols_clearbox() {
      RUN.modcontrols.takeitdown();
      this.busy = false;
    },
  });
});

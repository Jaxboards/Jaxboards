/* global RUN */
import Event from './JAX/event';
import gracefulDegrade from './JAX/graceful-degrade';
import { assign, onDOMReady } from './JAX/util';

// TODO: Find a place for this state
let onPageChangeOld;

const postIDs = function fetchPIDs(a) {
  let pids = [];
  if (a[0] && (typeof a[0] === 'string' || typeof a[0] === 'number')) {
    pids = `${a[0]}`.split(',');
  }
  const pl = pids ? pids.length : 0;
  const pluralPosts = pids.length === 1 ? '' : 's';
  const andPosts = pl ? ' and <br />' : '';
  return [pids, pl, pluralPosts, andPosts];
};

const threadIDs = function fetchTIDs(a) {
  let tids = [];
  if (a[1] && (typeof a[1] === 'string' || typeof a[1] === 'number')) {
    tids = `${a[1]}`.split(',');
  }
  const tl = tids ? tids.length : 0;
  const pluralThreads = tl === 1 ? '' : 's';
  return [tids, tl, pluralThreads];
};

class ModControls {
  constructor(commands) {
    assign(commands, {
      modcontrols_createModControls: (html) => {
        this.busy = true;
        this.createModControls(html);
      },

      modcontrols_postsync: (a) => {
        const [pids, pl, pluralPosts, andPosts] = postIDs(a);
        const [tids, tl, pluralThreads] = threadIDs(a);
        const html =
          `${
            "<form method='post' data-ajax-form='true'>" +
            "<input type='hidden' name='act' value='modcontrols' />"
          }${
            tl
              ? `${
                  "<select name='dot'>" +
                  "<option value='delete'>Delete</option>" +
                  "<option value='merge'>Merge</option>" +
                  "<option value='move'>Move</option>" +
                  "<option value='pin'>Pin</option>" +
                  "<option value='unpin'>Unpin</option>" +
                  "<option value='lock'>Lock</option>" +
                  "<option value='unlock'>Unlock</option>" +
                  '</select>' +
                  '&nbsp; &nbsp; <strong>'
                }${tl}</strong> topic${pluralThreads}${andPosts}`
              : ''
          }${
            pl
              ? `${
                  "<select name='dop'>" +
                  "<option value='delete'>Delete</option>" +
                  "<option value='move'>Move</option>" +
                  '</select> &nbsp; &nbsp; <strong>'
                }${pl}</strong> post${pluralPosts}`
              : ''
          }${
            pl && tl ? '<br />' : ' &nbsp; &nbsp; '
          }<input type='submit' value='Go' /> ` +
          "<input name='cancel' type='submit' " +
          "onclick='this.form.submitButton=this;' value='Cancel' /></form>";
        assign(this, {
          tids,
          tidl: tl,
          pids,
          pidl: pl,
        });
        if (tl || pl) this.createModControls(html);
        else this.destroyModControls();
      },

      modcontrols_move: (act) => {
        const whichone = parseInt((act && act[0]) || this.whichone, 10);
        if (!this.busy && onPageChangeOld) {
          onPageChangeOld = Event.onPageChange;
        }
        this.whichone = whichone;
        window.addEventListener('pushstate', this.boundCheckLocation);
        this.createModControls(
          `Ok, now browse to the ${
            whichone ? 'topic' : 'forum'
          } you want to move the ${
            whichone ? `${this.pidl} posts` : `${this.tidl} topics`
          } to...`,
        );
      },

      modcontrols_clearbox: () => {
        this.destroyModControls();
        this.busy = false;
      },
    });

    this.boundCheckLocation = () => this.checkLocation();
  }

  checkLocation() {
    const { whichone } = this;
    const regex = whichone ? /act=vt(\d+)/ : /act=vf(\d+)/;
    const locationMatch = document.location.toString().match(regex);
    if (locationMatch) {
      this.moveto(locationMatch[1]);
    } else {
      RUN.stream.commands.modcontrols_move();
    }
  }

  moveto(id) {
    const { whichone } = this;
    this.createModControls(
      `<form method="post" data-ajax-form="true">move ${
        whichone ? 'posts' : 'topics'
      } here? <input type="hidden" name="act" value="modcontrols" />` +
        `<input type="hidden" name="${
          whichone ? 'dop' : 'dot'
        }" value="moveto" /><input type="hidden" name="id" value="${id}" /><input type="submit" value="Yes" />` +
        '<input type="submit" name="cancel" value="Cancel" ' +
        'onclick="this.form.submitButton=this" /></form>',
    );
  }

  createModControls(html) {
    let modb = this.modb || document.querySelector('#modbox');
    if (!this.modb) {
      modb = document.createElement('div');
      modb.id = 'modbox';
      document.body.appendChild(modb);
    }
    modb.style.display = 'block';
    modb.innerHTML = html;
    gracefulDegrade(modb);
    this.modb = modb;
  }

  destroyModControls() {
    window.removeEventListener('pushstate', this.boundCheckLocation);
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
  RUN.modcontrols = new ModControls(RUN.stream.commands);
});

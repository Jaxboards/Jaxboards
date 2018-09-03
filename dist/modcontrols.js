(function () {
  'use strict';

  const { userAgent } = navigator;

  ({
    chrome: !!userAgent.match(/chrome/i),
    ie: !!userAgent.match(/msie/i),
    iphone: !!userAgent.match(/iphone/i),
    mobile: !!userAgent.match(/mobile/i),
    n3ds: !!userAgent.match(/nintendo 3ds/),
    firefox: !!userAgent.match(/firefox/i),
    safari: !!userAgent.match(/safari/i),
  });

  /**
   * This method adds some decoration to the default browser event.
   * This can probably be replaced with something more modern.
   */
  function Event(e) {
    const dB = document.body;
    const dE = document.documentElement;
    switch (e.keyCode) {
      case 13:
        e.ENTER = true;
        break;
      case 37:
        e.LEFT = true;
        break;
      case 38:
        e.UP = true;
        break;
      case 0.39:
        e.RIGHT = true;
        break;
      case 40:
        e.DOWN = true;
        break;
      default:
        break;
    }
    if (typeof e.srcElement === 'undefined') e.srcElement = e.target;
    if (typeof e.pageY === 'undefined') {
      e.pageY = e.clientY + (parseInt(dE.scrollTop || dB.scrollTop, 10) || 0);
      e.pageX = e.clientX + (parseInt(dE.scrollLeft || dB.scrollLeft, 10) || 0);
    }
    e.cancel = () => {
      e.returnValue = false;
      if (e.preventDefault) e.preventDefault();
      return e;
    };
    e.stopBubbling = () => {
      if (e.stopPropagation) e.stopPropagation();
      e.cancelBubble = true;
      return e;
    };
    return e;
  }

  // TODO: There are places in the source that are using this to store a callback
  // Refactor this
  Event.onPageChange = function onPageChange() {};

  /* global RUN */

  // This file is just a dumping ground until I can find better homes for these

  function assign(a, b) {
    Object.assign(a, b);
  }

  /**
   * Run a callback function either when the DOM is loaded and ready,
   * or immediately if the document is already loaded.
   * @param {Function} callback
   */
  function onDOMReady(callback) {
    if (document.readyState === 'complete') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  }

  /* global RUN */

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
        `<form method="post" onsubmit="return RUN.submitForm(this)">move ${
        whichone ? 'posts' : 'topics'
      } here? <input type="hidden" name="act" value="modcontrols" />`
          + `<input type="hidden" name="${
          whichone ? 'dop' : 'dot'
        }" value="moveto" /><input type="hidden" name="id" value="${
          id
        }" /><input type="submit" value="Yes" />`
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
          pids = (`${a[0]}`).split(',');
        }
        const pl = pids ? pids.length : 0;
        let tids = [];
        if (a[1] && (typeof a[1] === 'string' || typeof a[1] === 'number')) {
          tids = (`${a[1]}`).split(',');
        }
        const tl = tids ? tids.length : 0;
        const html = `${"<form method='post' onsubmit='return RUN.submitForm(this)'>"
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
            + '&nbsp; &nbsp; <strong>'}${
            tl
          }</strong> topic${
            tl > 1 ? 's' : ''
          }${pl ? ' and <br />' : ''}`
          : ''
      }${pl
        ? `${"<select name='dop'>"
            + "<option value='delete'>Delete</option>"
            + "<option value='move'>Move</option>"
            + '</select> &nbsp; &nbsp; <strong>'}${
          pl
        }</strong> post${
          pids.length > 1 ? 's' : ''}`
        : ''
      }${pl && tl ? '<br />' : ' &nbsp; &nbsp; '
      }<input type='submit' value='Go' /> `
          + '<input name=\'cancel\' type=\'submit\' '
          + 'onclick=\'this.form.submitButton=this;\' value=\'Cancel\' /></form>';
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
        const whichone = parseInt(a && a[0] ? a[0] : RUN.modcontrols.whichone, 10);
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

}());

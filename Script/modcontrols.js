OnDomReady(() => {
  RUN.modcontrols = {
    checklocation() {
      const whichone = RUN.modcontrols.whichone;
      regex = whichone ? /act=vt(\d+)/ : /act=vf(\d+)/;
      if (document.location.toString().match(regex)) {
        RUN.modcontrols.moveto(whichone, RegExp.$1);
      } else {
        RUN.stream.commands.modcontrols_move();
      }
    },
    moveto(whichone, id) {
      RUN.modcontrols.getitup(
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
    },
    getitup(html) {
      var html;
      let modb = document.querySelector('#modbox');
      if (!modb) {
        modb = document.createElement('div');
        modb.id = 'modbox';
        document.body.appendChild(modb);
      }
      modb.style.display = 'block';
      modb.innerHTML = html;
    },
    takeitdown() {
      const modb = document.querySelector('#modbox');
      if (JAX.event.onPageChangeOld) {
        JAX.event.onPageChange = JAX.event.onPageChangeOld;
        JAX.event.onPageChangeOld = null;
      } else JAX.event.onPageChange = null;
      if (modb) {
        modb.innerHTML = '';
        modb.style.display = 'none';
      }
    },
    togbutton(button) {
      button.classList.toggle('selected');
    },
  };
});
OnDomReady(() => {
  JAX.assign(RUN.stream.commands, {
    modcontrols_sayhi(a) {
      alert('this is a test');
    },

    modcontrols_getitup(html) {
      this.busy = true;
      RUN.modcontrols.getitup(html);
    },

    modcontrols_postsync(a) {
      const i = 0;
      const temp = 0;
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
      JAX.assign(RUN.modcontrols, {
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
      if (!this.busy && JAX.event.onPageChangeOld) {
        JAX.event.onPageChangeOld = JAX.event.onPageChange;
      }
      RUN.modcontrols.whichone = whichone;
      JAX.event.onPageChange = RUN.modcontrols.checklocation;
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

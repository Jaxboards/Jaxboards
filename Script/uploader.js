/* eslint-disable */
// TODO: Remove this file

class Uploader {
  constructor() {
    this.uploaders = [];
  }

  listenerHandler(id, action, args) {
    let tmp;
    // moving arguments around
    switch (action) {
      case 'addfile':
        args[0].id = args[1];
        args = args[0];
        args.upload = function (url) {
          Uploader.upload(id, this, url);
        };
        args = [args];
        break;
      case 'startupload':
        args[0].id = args[1];
        args = [args[0]];
        break;
      case 'progress':
        args[0].id = args[1];
        args.splice(1, 1);
        break;
      case 'error':
        args[2].id = args.pop();
        break;
      default:
        if (!args.length) args = [args];
        break;
    }
    if (this.uploaders[id] && this.uploaders[id][action]) {
      this.uploaders[id][action].apply(this.uploaders[id], args);
    }
  }

  createButton() {
    const d = document.createElement('div');
    d.className = 'uploadbutton';
    d.innerHTML = 'Add File(s)';
    return [d, this.create(d)];
  }

  create(el, w, h, url) {
    const nid = this.uploaders.length;
    const swf = JAX.SWF('Script/uploader.swf', `uploader${nid}`, {
      width: w || '100%',
      height: h || '100%',
      allowScriptAccess: 'sameDomain',
      wmode: 'transparent',
      flashvars: `id=${nid}`,
    });

    const s = swf.style;
    s.position = 'absolute';
    s.left = '0px';
    s.top = '0px';
    el.style.position = 'relative';
    el.appendChild(swf);
    this.uploaders.push([]);
    this.uploaders[nid].flashObj = swf;
    this.uploaders[nid].id = nid;
    return this.uploaders[nid];
  }

  upload(nid, fileobj, url) {
    this.uploaders[nid].flashObj.upload(fileobj.id, url);
  }
}

// Uploader is a singleton
export default new Uploader();

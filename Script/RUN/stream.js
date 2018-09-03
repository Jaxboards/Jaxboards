import Ajax from '../JAX/ajax';
import Commands from './commands';

class Stream {
  constructor() {
    this.request = new Ajax({
      callback: request => this.handleRequestData(request),
    });
    this.lastURL = '';
    this.commands = Commands;
  }

  handleRequestData(xmlobj) {
    if (xmlobj.status !== 200) return;
    xmlobj.parsed = true;
    let { responseText } = xmlobj;
    const debug = document.querySelector('#debug');
    let softurl = false;
    if (typeof responseText !== 'string') responseText = '';
    if (debug) {
      debug.innerHTML = `<xmp>${responseText}</xmp>`;
    }
    let x;
    let cmds = [];
    if (responseText.length) {
      try {
        // TODO: try and remove this eval?
        // eslint-disable-next-line
        cmds = eval(`(${responseText})`);
      } catch (e) {
        cmds = [];
      }
      cmds.forEach((cmd) => {
        if (cmd === 'softurl') {
          softurl = true;
        } else if (this.commands[cmd]) {
          this.commands[cmd](cmds[x]);
        }
      });
    }
    if (xmlobj.type >= 2) {
      const a = xmlobj.url.substring(1);
      if (!softurl) {
        document.location = `#${a}`;
        this.lastURL = a;
        if (Event.onPageChange) Event.onPageChange();
      } else if (document.location.hash.substring(1) === a) document.location = '#';
    }
    this.donext();
  }

  location(path, b) {
    let a = path.split('?');
    a = a[1] || a[0];
    this.load(`?${a}`, null, null, null, b || 2);
    this.busy = true;
    return false;
  }

  loader() {
    this.load(`?${this.lastURL}`);
    return true;
  }

  updatePage() {
    // this function makes the back/forward buttons actually do something,
    // using anchors
    const location = document.location.hash.substring(1) || '';
    if (location !== this.lastURL) {
      this.location(location, '3');
    }
  }
}

export default Stream;

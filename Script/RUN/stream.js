import Ajax from '../JAX/ajax';
import Commands from './commands';

const UPDATE_INTERVAL = 5000;

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
    let cmds = [];
    if (responseText.length) {
      try {
        cmds = JSON.parse(responseText);
      } catch (e) {
        cmds = [];
      }
      cmds.forEach(([cmd, ...args]) => {
        if (cmd === 'softurl') {
          softurl = true;
        } else if (this.commands[cmd]) {
          this.commands[cmd](args);
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
    this.pollData();
  }

  location(path, requestType = 2) {
    let a = path.split('?');
    a = a[1] || a[0];
    this.request.load(`?${a}`, { requestType });
    this.busy = true;
    return false;
  }

  load(...args) {
    this.request.load(...args);
  }

  loader() {
    this.request.load(`?${this.lastURL}`);
    return true;
  }

  pollData(isEager) {
    if (isEager) {
      this.loader();
    }
    clearTimeout(this.timeout);
    if (document.cookie.match(`actw=${window.name}`)) {
      this.timeout = setTimeout(() => this.loader(), UPDATE_INTERVAL);
    }
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

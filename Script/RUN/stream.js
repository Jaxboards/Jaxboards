import Ajax from '../JAX/ajax';
import Commands from './commands';

const UPDATE_INTERVAL = 5000;

class Stream {
    constructor() {
        this.request = new Ajax({
            callback: (request) => this.handleRequestData(request),
        });
        this.lastURL = document.location.search.substr(1);
        this.commands = Commands;
    }

    handleRequestData(xmlobj) {
        if (xmlobj.status !== 200) return;
        xmlobj.parsed = true;
        let { responseText } = xmlobj;
        let softurl = false;
        if (typeof responseText !== 'string') responseText = '';
        let cmds = [];
        if (responseText.length) {
            try {
                cmds = JSON.parse(responseText);
            } catch (e) {
                // eslint-disable-next-line no-console
                console.error(e);
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
        if (xmlobj.type === 2) {
            const queryParams = xmlobj.url.substring(1);
            if (!softurl) {
                window.history.pushState(
                    { queryParams },
                    '',
                    `?${queryParams}`,
                );
                // pushstate is not a real browser event unfortunately, so I have to trigger it myself
                window.dispatchEvent(new Event('pushstate'));
                this.lastURL = queryParams;
            }
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

    updatePage(queryParams) {
        // this function makes the back/forward buttons actually do something,
        // using anchors
        if (queryParams !== this.lastURL) {
            this.location(queryParams, 3);
        }
    }
}

export default Stream;

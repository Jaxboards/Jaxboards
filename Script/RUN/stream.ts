import Ajax from '../JAX/ajax';
import Commands from './commands';

const UPDATE_INTERVAL = 5000;

class Stream {
    private request: Ajax;

    private commands: typeof Commands;

    private lastURL: string;

    private timeout: number = 0;

    constructor() {
        this.request = new Ajax({
            callback: (request: XMLHttpRequest) =>
                this.handleRequestData(request),
        });
        this.lastURL = `${document.location.pathname}${document.location.search}`;
        this.commands = Commands;
    }

    handleRequestData(xmlobj: XMLHttpRequest & { requestType?: number }) {
        if (xmlobj.status !== 200) return;
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
            cmds.forEach(
                ([cmd, ...args]: [
                    keyof typeof Commands | 'softurl',
                    ...Array<number | string>,
                ]) => {
                    if (cmd === 'softurl') {
                        softurl = true;
                    } else if (this.commands[cmd]) {
                        this.commands[cmd](...args);
                    }
                },
            );
        }

        if (xmlobj.requestType === 2) {
            if (!softurl) {
                globalThis.history.pushState(
                    { lastURL: xmlobj.responseURL },
                    '',
                    xmlobj.responseURL,
                );
                // pushstate is not a real browser event unfortunately, so I have to trigger it myself
                globalThis.dispatchEvent(new Event('pushstate'));
                this.lastURL = xmlobj.responseURL;
            }
        }
        this.pollData();
    }

    location(path: string, requestType = 2) {
        this.request.load(path, { requestType });
    }

    load(...args: Parameters<typeof this.request.load>) {
        this.request.load(...args);
    }

    pollData(isEager = false) {
        if (isEager) {
            this.request.load(this.lastURL);
        }
        clearTimeout(this.timeout);
        if (document.cookie.includes(`actw=${globalThis.name}`)) {
            this.timeout = setTimeout(
                () => this.request.load(this.lastURL),
                UPDATE_INTERVAL,
            );
        }
    }

    updatePage(lastURL: string) {
        // this function makes the back/forward buttons actually do something,
        // using anchors
        if (lastURL !== this.lastURL) {
            this.location(lastURL, 3);
        }
    }
}

export default Stream;

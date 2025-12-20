import { buildQueryString } from '../JAX/buildQueryString';
import Commands from './commands';

const UPDATE_INTERVAL = 5000;

class Stream {
    private commands: typeof Commands;

    private lastURL: string;

    private timeout: number = 0;

    constructor() {
        this.lastURL = `${document.location.pathname}${document.location.search}`;
        this.commands = Commands;
    }

    handleRequestData(url: string, cmds: Array<any>, requestType = 1) {
        let softurl = false;
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

        if (requestType === 2) {
            if (!softurl) {
                globalThis.history.pushState({ lastURL: url }, '', url);
                // pushstate is not a real browser event unfortunately, so I have to trigger it myself
                globalThis.dispatchEvent(new Event('pushstate'));
                this.lastURL = url;
            }
        }
        this.pollData();
    }

    location(path: string, requestType = 2) {
        this.load(path, { requestType });
    }

    async load(
        url: string,
        {
            data,
            method = 'POST',
            requestType = 1,
        }: {
            data?: Record<string, string> | [string[], string[]];
            method?: string;
            requestType?: number;
        } = {},
    ) {
        let body: string | undefined;

        if (Array.isArray(data)) {
            body = buildQueryString(data[0], data[1]);
        } else if (data) {
            body = buildQueryString(data);
        }

        const request = await fetch(url, {
            method,
            body,
            headers: {
                'X-JSACCESS': `${requestType}`,
                'Content-Type': 'application/x-www-form-urlencoded',
            },
        });

        if (request.ok) {
            const json = await request.json();
            this.handleRequestData(url, json, requestType);
        }
    }

    pollData(isEager = false) {
        if (isEager) {
            this.load(this.lastURL);
        }
        clearTimeout(this.timeout);
        if (document.cookie.includes(`actw=${globalThis.name}`)) {
            this.timeout = setTimeout(
                () => this.load(this.lastURL),
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

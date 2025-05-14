/**
 * For some reason, I designed this method
 * to accept Objects (key/value pairs)
 * or 2 arguments:  keys and values
 * The purpose is to construct data to send over URL or POST data
 *
 * @example
 * buildQueryString({key: 'value', key2: 'value2'}) === 'key=value&key2=value2';
 *
 * @example
 * buildQueryString(['key', 'key2'], ['value, 'value2']) === 'key=value&key2=value2'
 *
 * @return {String}
 */
function buildQueryString(
    keys: Record<string, string> | string[],
    values?: string[],
): string {
    if (!keys) {
        return '';
    }
    if (Array.isArray(keys)) {
        if (!values) {
            throw new Error(
                'Invalid arguments for buildQueryString. Received array and undefined',
            );
        }
        return keys
            .map(
                (key, index) =>
                    `${encodeURIComponent(key)}=${encodeURIComponent(
                        values[index] || '',
                    )}`,
            )
            .join('&');
    }

    return Object.keys(keys)
        .map(
            (key) =>
                `${encodeURIComponent(key)}=${encodeURIComponent(keys[key] || '')}`,
        )
        .join('&');
}

type AjaxSettings = {
    callback?: (req: XMLHttpRequest) => void;
    data?: Record<string, string> | [string[], string[]] | string;
    method?: string;
    requestType?: number;
    readyState?: number;
};

class Ajax {
    setup: AjaxSettings = {
        readyState: 4,
        method: 'POST',
    };

    constructor(setup = {}) {
        Object.assign(this.setup, setup);
    }

    load(
        url: string,
        { callback, data, method = 'POST', requestType = 1 }: AjaxSettings = {
            method: 'POST',
            requestType: 1,
        },
    ) {
        // requestType is an enum (1=update, 2=load new)
        let sendData = null;

        if (data && Array.isArray(data)) {
            sendData = buildQueryString(data[0], data[1]);
        } else if (data && typeof data !== 'string') {
            sendData = buildQueryString(data);
        }

        const request = new XMLHttpRequest();
        if (callback) {
            this.setup.callback = callback;
        }
        request.onreadystatechange = () => {
            if (request.readyState === this.setup.readyState) {
                this.setup.callback?.(request);
            }
        };

        request.open(method, url, true);
        Object.assign(request, { url, requestType });

        if (method) {
            request.setRequestHeader(
                'Content-Type',
                'application/x-www-form-urlencoded',
            );
        }

        request.setRequestHeader('X-JSACCESS', `${requestType}`);
        request.send(sendData);
        return request;
    }
}

export default Ajax;

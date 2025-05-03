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
function buildQueryString(keys, values) {
    if (!keys) {
        return '';
    }
    if (values) {
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

class Ajax {
    constructor(s) {
        this.setup = {
            readyState: 4,
            callback() {},
            method: 'POST',
            ...s,
        };
    }

    load(
        url,
        { callback, data, method = this.setup.method, requestType = 1 } = {},
    ) {
        // requestType is an enum (1=update, 2=load new)
        let sendData = null;

        if (
            data &&
            Array.isArray(data) &&
            Array.isArray(data[0]) &&
            data[0].length === data[1].length
        ) {
            sendData = buildQueryString(data[0], data[1]);
        } else if (typeof data !== 'string') {
            sendData = buildQueryString(data);
        }

        const request = new XMLHttpRequest();
        if (callback) {
            this.setup.callback = callback;
        }
        request.onreadystatechange = () => {
            if (request.readyState === this.setup.readyState) {
                this.setup.callback(request);
            }
        };

        request.open(method, url, true);
        request.url = url;
        request.type = requestType;

        if (method) {
            request.setRequestHeader(
                'Content-Type',
                'application/x-www-form-urlencoded',
            );
        }

        request.setRequestHeader('X-JSACCESS', requestType);
        request.send(sendData);
        return request;
    }
}

export default Ajax;

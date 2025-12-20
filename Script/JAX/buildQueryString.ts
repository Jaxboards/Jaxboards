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
// eslint-disable-next-line import/prefer-default-export
export function buildQueryString(
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

function humanReadableSize(bytes, dp = 1) {
    const thresh = 1024;

    if (Math.abs(bytes) < thresh) {
        return bytes + ' B';
    }

    const units = ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
    let u = -1;
    const r = 10**dp;

    do {
        bytes /= thresh;
        ++u;
    } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);

    return bytes.toFixed(dp) + ' ' + units[u];
}

const removeParameter = (key) => {
    const url = new URL(window.location);
    url.searchParams.delete(key);
    window.history.replaceState(null, '', url);
}

const getParameter = (key) => {
    const url = new URL(window.location);
    if (url.searchParams.has(key)) {
        return decodeURIComponent(url.searchParams.get(key));
    } else {
        return null;
    }
}

const encodeText = (str) => {
    return Uint8Array.from(str, c => c.charCodeAt(0));
}

const decodeText = (buffer) => {
    return String.fromCharCode(...new Uint8Array(buffer));
}

const getBaseUrl = () => {
    return window.location.protocol + "//" + window.location.host;
}

export { humanReadableSize, removeParameter, getParameter, encodeText, decodeText, getBaseUrl };
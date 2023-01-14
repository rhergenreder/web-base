import {format, parse} from "date-fns";
import {API_DATE_FORMAT, API_DATETIME_FORMAT} from "./constants";

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

const formatDate = (L, apiDate) => {
    if (!(apiDate instanceof Date)) {
        if (!isNaN(apiDate)) {
            apiDate = new Date(apiDate);
        } else {
            apiDate = parse(apiDate, API_DATE_FORMAT, new Date());
        }
    }

    return format(apiDate, L("general.date_format", "YYY/MM/dd"));
}

const formatDateTime = (L, apiDate) => {
    if (!(apiDate instanceof Date)) {
        if (!isNaN(apiDate)) {
            apiDate = new Date(apiDate);
        } else {
            apiDate = parse(apiDate, API_DATETIME_FORMAT, new Date());
        }
    }

    return format(apiDate, L("general.date_time_format", "YYY/MM/dd HH:mm:ss"));
}

const upperFirstChars = (str) => {
    return str.split(" ")
        .map(block => block.charAt(0).toUpperCase() + block.substring(1))
        .join(" ");
}

export { humanReadableSize, removeParameter, getParameter, encodeText, decodeText, getBaseUrl,
    formatDate, formatDateTime, upperFirstChars };
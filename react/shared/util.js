import {format, parse, formatDistance as formatDistanceDateFns } from "date-fns";
import {API_DATE_FORMAT, API_DATETIME_FORMAT} from "./constants";

function createDownload(name, data) {
    const url = window.URL.createObjectURL(new Blob([data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', name);
    link.setAttribute("target", "_blank");
    document.body.appendChild(link);
    link.click();
    link.remove();
}

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

const toDate = (apiDate, apiFormat = API_DATETIME_FORMAT) => {
    if (apiDate === null) {
        return "";
    } else if (!(apiDate instanceof Date)) {
        if (!isNaN(apiDate)) {
            apiDate = new Date(apiDate * 1000);
        } else {
            apiDate = parse(apiDate, apiFormat, new Date());
        }
    }

    return apiDate;
}

const formatDate = (L, apiDate) => {
    return format(toDate(apiDate), L("general.datefns_date_format", "YYY/MM/dd"));
}

const formatDateTime = (L, apiDate, precise=false) => {
    let dateFormat = precise ?
        L("general.datefns_datetime_format_precise", "YYY/MM/dd HH:mm:ss") :
        L("general.datefns_datetime_format", "YYY/MM/dd HH:mm");
    return format(toDate(apiDate), dateFormat);
}

function formatDistance(dateFns, apiDate) {
    return formatDistanceDateFns(toDate(apiDate), new Date(), { addSuffix: true, locale: dateFns });
}


const upperFirstChars = (str) => {
    return str.split(" ")
        .map(block => block.charAt(0).toUpperCase() + block.substring(1))
        .join(" ");
}

const isInt = (value) => {
    return !isNaN(value) &&
        parseInt(Number(value)) === value &&
        !isNaN(parseInt(value, 10));
}

export { humanReadableSize, removeParameter, getParameter,
    encodeText, decodeText, getBaseUrl,
    formatDate, formatDateTime, formatDistance,
    upperFirstChars, isInt, createDownload };
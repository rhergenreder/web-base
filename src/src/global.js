import moment from 'moment';

function getPeriodString(date) {
    return moment(date).fromNow();
}

export { getPeriodString };
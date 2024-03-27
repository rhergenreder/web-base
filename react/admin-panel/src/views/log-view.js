import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Link} from "react-router-dom";
import usePagination from "shared/hooks/pagination";
import {DataColumn, DataTable, DateTimeColumn, NumericColumn, StringColumn} from "shared/elements/data-table";
import {TextField} from "@mui/material";
import {DesktopDateTimePicker} from "@mui/x-date-pickers";
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import {API_DATETIME_FORMAT} from "shared/constants";
import {format, toDate} from "date-fns";

export default function LogView(props) {

    // meta
    const api = props.api;
    const showDialog = props.showDialog;
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const pagination = usePagination();

    // data
    const LOG_LEVELS = ['debug', 'info', 'warning', 'error', 'severe'];
    const [logEntries, setLogEntries] = useState([]);

    // filters
    const [logLevel, setLogLevel] = useState(2);
    const [timestamp, setTimestamp] = useState(null);
    const [query, setQuery] = useState("");
    const [forceReload, setForceReload] = useState(0);

    useEffect(() => {
        requestModules(props.api, ["general"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchLogs = useCallback((page, count, orderBy, sortOrder) => {
        api.fetchLogEntries(page, count, orderBy, sortOrder,
                LOG_LEVELS[logLevel],
                timestamp ? format(timestamp, API_DATETIME_FORMAT) : null,
                query
        ).then((res) => {
            if (res.success) {
                setLogEntries(res.logs);
                pagination.update(res.pagination);
            } else {
                showDialog(res.msg, "Error fetching log entries");
                return null;
            }
        });
    }, [api, showDialog, logLevel, timestamp, query]);

    useEffect(() => {
        // TODO: wait for user to finish typing before force reloading
        setForceReload(forceReload + 1);
    }, [query, timestamp, logLevel]);

    const messageColumn = (() => {
        let column = new DataColumn(L("message"), "message");
        column.renderData = (L, entry) => {
            return <pre>{entry.message}</pre>
        }
        return column;
    })();

    const columnDefinitions = [
        new NumericColumn(L("general.id"), "id"),
        new StringColumn(L("module"), "module"),
        new StringColumn(L("severity"), "severity"),
        new DateTimeColumn(L("timestamp"), "timestamp", { precise: true }),
        messageColumn,
    ];

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>System Log</h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">System Log</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div className={"content overflow-auto"}>
                <div className={"row p-2"}>
                    <div className={"col-2"}>
                        <div className={"form-group"}>
                            <label>{L("log.severity")}</label>
                            <select className={"form-control"} value={logLevel} onChange={e => setLogLevel(parseInt(e.target.value))}>
                                {LOG_LEVELS.map((value, index) =>
                                    <option key={"option-" + value} value={index}>{value}</option>)
                                }
                            </select>
                        </div>
                    </div>
                    <div className={"col-4"}>
                        <div className={"form-group"}>
                            <label>{L("log.timestamp")}</label>
                            <LocalizationProvider dateAdapter={AdapterDateFns}>
                                <DesktopDateTimePicker className={"form-control"}
                                                label={L("Select date time to filter...")}
                                                value={timestamp ? toDate(new Date()) : null}
                                                format={L("general.datefns_datetime_format_precise")}
                                                onChange={(newValue) => setTimestamp(newValue)}
                                                slotProps={{ textField: { size: 'small' } }}
                                />
                            </LocalizationProvider>
                        </div>
                    </div>
                    <div className={"col-6"}>
                        <div className={"form-group"}>
                            <label>{L("log.query")}</label>
                            <TextField
                                className={"form-control"}
                                placeholder={L("log.search_query") + "…"}
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                variant={"outlined"}
                                size={"small"}/>
                        </div>
                    </div>
                </div>
                <div className={"container-fluid"}>
                    <DataTable
                        data={logEntries}
                        pagination={pagination}
                        className={"table table-striped"}
                        fetchData={onFetchLogs}
                        forceReload={forceReload}
                        defaultSortColumn={3}
                        defaultSortOrder={"desc"}
                        placeholder={"No log entries to display"}
                        columns={columnDefinitions} />
                </div>
            </div>
        </div>
    </>
}
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Link} from "react-router-dom";
import usePagination from "shared/hooks/pagination";
import {DataColumn, DataTable, DateTimeColumn, NumericColumn, StringColumn} from "shared/elements/data-table";
import {Box, FormControl, FormGroup, FormLabel, Grid, IconButton, MenuItem, TextField} from "@mui/material";
import {DateTimePicker} from "@mui/x-date-pickers";
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import {API_DATETIME_FORMAT} from "shared/constants";
import {format, toDate} from "date-fns";
import {ExpandLess, ExpandMore} from "@mui/icons-material";
import ViewContent from "../elements/view-content";

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
        requestModules(props.api, ["general", "logs"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchLogs = useCallback((page, count, orderBy, sortOrder) => {
        let apiTimeStamp = null;
        try {
            if (timestamp) {
                apiTimeStamp = format(timestamp, API_DATETIME_FORMAT);
            }
        } catch (e) {
            apiTimeStamp = null;
        }

        api.fetchLogEntries(page, count, orderBy, sortOrder,
                LOG_LEVELS[logLevel], apiTimeStamp, query).then((res) => {
            if (res.success) {
                setLogEntries(res.logs);
                pagination.update(res.pagination);
            } else {
                showDialog(res.msg, L("logs.fetch_logs_error"));
                return null;
            }
        });
    }, [api, showDialog, logLevel, timestamp, query]);

    const onToggleDetails = useCallback(entry => {
        let newLogEntries = [...logEntries];
        for (const logEntry of newLogEntries) {
            if (logEntry.id === entry.id) {
                logEntry.showDetails = !logEntry.showDetails;
            }
        }
        setLogEntries(newLogEntries);
    }, [logEntries]);

    useEffect(() => {
        // TODO: wait for user to finish typing before force reloading
        setForceReload(forceReload + 1);
    }, [query, timestamp, logLevel]);

    const messageColumn = (() => {
        let column = new DataColumn(L("logs.message"), "message");
        column.sortable = false;
        column.renderData = (L, entry) => {
            let lines = entry.message.trim().split("\n");
            return <Box display={"grid"} gridTemplateColumns={"40px auto"}>
                    <Box alignSelf={"top"} textAlign={"center"}>
                        {lines.length > 1 &&
                            <IconButton size={"small"} onClick={() => onToggleDetails(entry)}
                                        title={L(entry.showDetails ? "logs.hide_details" : "logs.show_details")}>
                                {entry.showDetails ? <ExpandLess /> : <ExpandMore />}
                            </IconButton>
                        }
                    </Box>
                    <Box alignSelf={"center"}>
                        <pre>
                            {entry.showDetails ? entry.message : lines[0]}
                        </pre>
                    </Box>
                </Box>
        }
        return column;
    })();

    const columnDefinitions = [
        new NumericColumn(L("general.id"), "id"),
        new StringColumn(L("logs.module"), "module"),
        new StringColumn(L("logs.severity"), "severity"),
        new DateTimeColumn(L("logs.timestamp"), "timestamp", { precise: true }),
        messageColumn,
    ];

    return <ViewContent title={L("logs.title")} path={[
        <Link key={"dashboard"} to={"/admin/dashboard"}>Home</Link>,
        <span key={"logs"}>{L("logs.title")}</span>
    ]}>
        <Grid container spacing={2}>
            <Grid item xs={2}>
                <FormGroup>
                    <FormLabel>{L("logs.severity")}</FormLabel>
                    <FormControl>
                        <TextField select variant={"outlined"} size={"small"} value={logLevel}
                                   onChange={e => setLogLevel(parseInt(e.target.value))}
                                   inputProps={{size: "small"}}>
                            {LOG_LEVELS.map((value, index) =>
                                <MenuItem key={"option-" + value} value={index}>{value}</MenuItem>)
                            }
                        </TextField>
                    </FormControl>
                </FormGroup>
            </Grid>
            <Grid item xs={4}>
                <FormGroup>
                    <FormLabel>{L("logs.timestamp")}</FormLabel>
                    <FormControl>
                        <LocalizationProvider dateAdapter={AdapterDateFns}>
                            <DateTimePicker label={L("logs.timestamp_placeholder") + "…"}
                                            value={timestamp ? toDate(new Date()) : null}
                                            format={L("general.datefns_datetime_format_precise")}
                                            onChange={(newValue) => setTimestamp(newValue)}
                                            slotProps={{textField: {size: 'small'}}}
                                            sx={{"& .MuiInputBase-input": {height: "23px", padding: 1}}}
                            />
                        </LocalizationProvider>
                    </FormControl>
                </FormGroup>
            </Grid>
            <Grid item xs={6}>
                <FormGroup>
                    <FormLabel>{L("logs.search")}</FormLabel>
                    <FormControl>
                        <TextField
                            placeholder={L("logs.search_query") + "…"}
                            value={query}
                            onChange={e => setQuery(e.target.value)}
                            variant={"outlined"}
                            size={"small"}/>
                    </FormControl>
                </FormGroup>
            </Grid>
        </Grid>
        <DataTable
            data={logEntries}
            pagination={pagination}
            fetchData={onFetchLogs}
            forceReload={forceReload}
            defaultSortColumn={3}
            defaultSortOrder={"desc"}
            placeholder={L("logs.no_entries_placeholder")}
            columns={columnDefinitions}/>
    </ViewContent>
}
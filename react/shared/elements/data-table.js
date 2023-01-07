import {Table, TableBody, TableCell, TableHead, TableRow} from "@material-ui/core";
import ArrowUpwardIcon from "@material-ui/icons/ArrowUpward";
import ArrowDownwardIcon from "@material-ui/icons/ArrowDownward";
import React, {useCallback, useContext, useEffect, useState} from "react";
import usePagination from "../hooks/pagination";
import {parse} from "date-fns";
import "./data-table.css";
import {LocaleContext} from "../locale";
import clsx from "clsx";
import {Box} from "@mui/material";


export function DataTable(props) {

    const { className, placeholder,
        fetchData, onClick, onFilter,
        defaultSortColumn, defaultSortOrder,
        columns, ...other } = props;

    const {currentLocale, requestModules, translate: L} = useContext(LocaleContext);

    const [doFetchData, setFetchData] = useState(false);
    const [data, setData] = useState(null);
    const [sortAscending, setSortAscending] = useState(["asc","ascending"].includes(defaultSortOrder?.toLowerCase));
    const [sortColumn, setSortColumn] = useState(defaultSortColumn || null);
    const pagination = usePagination();
    const sortable = props.hasOwnProperty("sortable") ? !!props.sortable : true;

    const onFetchData = useCallback((force = false) => {
        if (doFetchData || force) {
            setFetchData(false);
            const orderBy = columns[sortColumn]?.field || null;
            const sortOrder = sortAscending ? "asc" : "desc";
            fetchData(pagination.getPage(), pagination.getPageSize(), orderBy, sortOrder).then(([data, dataPagination]) => {
                if (data) {
                    setData(data);
                    pagination.update(dataPagination);
                }
            });
        }
    }, [doFetchData, columns, sortColumn, sortAscending, pagination]);

    // pagination changed?
    useEffect(() => {
        let forceFetch = (pagination.getPageSize() < pagination.getTotal());
        onFetchData(forceFetch);
    }, [pagination.data.pageSize, pagination.data.current]);

    // sorting changed
    useEffect(() => {
        onFetchData(true);
    }, [sortAscending, sortColumn]);

    let headerRow = [];
    const onChangeSort = useCallback((index, column) => {
        if (sortable && column.sortable) {
            if (sortColumn === index) {
                setSortAscending(!sortAscending);
            } else {
                setSortColumn(index);
            }
        }
    }, [onFetchData, sortColumn, sortAscending]);

    for (const [index, column] of columns.entries()) {
        if (!(column instanceof DataColumn)) {
            throw new Error("DataTable can only have DataColumn-objects as column definition, got: " + typeof column);
        }

        if (sortable && column.sortable) {
            headerRow.push(<TableCell key={"col-" + index} className={"sortable"}
                                      title={L("general.sort_by") + ": " + column.label}
                                      onClick={() => onChangeSort(index, column) }>
                {sortColumn === index ? (sortAscending ? <ArrowUpwardIcon /> : <ArrowDownwardIcon />): <></>}{column.renderHead(index)}
            </TableCell>);
        } else {
            headerRow.push(<TableCell key={"col-" + index}>
                {column.renderHead(index)}
            </TableCell>);
        }
    }

    const numColumns = columns.length;
    let rows = [];
    if (data) {
        for (const [key, entry] of Object.entries(data)) {
            let row = [];
            for (const [index, column] of columns.entries()) {
                row.push(<TableCell key={"col-" + index}>{column.renderData(entry)}</TableCell>);
            }

            rows.push(<TableRow key={"row-" + key}>{ row }</TableRow>);
        }
    } else if (placeholder) {
        rows.push(<TableRow key={"row-placeholder"}>
            <TableCell colSpan={numColumns}>
                { placeholder }
            </TableCell>
        </TableRow>);
    }

    /*


    let columnElements = [];
    if (columns) {
        for (const [key, column] of Object.entries(columns)) {
            const centered = column.alignment === "center";
            const sortable = doSort && (!column.hasOwnProperty("sortable") || !!column.sortable);
            const label = column.label;

            if (!sortable) {
                columnElements.push(
                    <TableCell key={"column-" + key} className={clsx(centered && classes.columnCenter)}>
                        { label }
                    </TableCell>
                );
            } else {
                columnElements.push(
                    <TableCell key={"column-" + key} label={L("Sort By") + ": " + label} className={clsx(classes.clickable, centered && classes.columnCenter)}
                               onClick={() => (key === sortColumn ? setSortAscending(!sortAscending) : setSortColumn(key)) }>
                        { key === sortColumn ?
                            <Grid container alignItems={"center"} spacing={1} direction={"row"} className={classes.gridSorted}>
                                <Grid item>{ sortAscending ? <ArrowUpwardIcon fontSize={"small"} /> : <ArrowDownwardIcon fontSize={"small"} /> }</Grid>
                                <Grid item>{ label }</Grid>
                                <Grid item/>
                            </Grid> :
                            <span><i/>{label}</span>
                        }
                    </TableCell>
                );
            }
        }
    }

    const getValue = useCallback((entry, key) => {
        if (typeof columns[key]?.value === 'function') {
            return columns[key].value(entry);
        } else {
            return entry[columns[key]?.value] ?? null;
        }
    }, [columns]);

    let numColumns = columns ? Object.keys(columns).length : 0;

    const compare = (a,b,callback) => {
        let definedA = a !== null && typeof a !== 'undefined';
        let definedB = b !== null && typeof b !== 'undefined';
        if (!definedA && !definedB) {
            return 0;
        } else if (!definedA) {
            return 1;
        } else if (!definedB) {
            return -1;
        } else {
            return callback(a,b);
        }
    }

    let rows = [];
    const hasClickHandler = typeof onClick === 'function';
    if (data !== null && columns) {
        let hidden = 0;
        let sortedEntries = data.slice();

        if (sortColumn && columns[sortColumn]) {
            let sortFunction;
            if (typeof columns[sortColumn]?.compare === 'function') {
                sortFunction = columns[sortColumn].compare;
            } else if (columns[sortColumn]?.type === Date) {
                sortFunction = (a, b) => compare(a, b, (a,b) => a.getTime() - b.getTime());
            } else if (columns[sortColumn]?.type === Number) {
                sortFunction = (a, b) => compare(a, b, (a,b) => a - b);
            } else {
                sortFunction = ((a, b) =>
                    compare(a, b, (a,b) => a.toString().toLowerCase().localeCompare(b.toString().toLowerCase()))
                )
            }

            sortedEntries.sort((a, b) => {
                let entryA = getValue(a, sortColumn);
                let entryB = getValue(b, sortColumn);
                return sortFunction(entryA, entryB);
            });

            if (!sortAscending) {
                sortedEntries = sortedEntries.reverse();
            }
        }

        Array.from(Array(sortedEntries.length).keys()).forEach(rowIndex => {
            if (typeof props.filter === 'function' && !props.filter(sortedEntries[rowIndex])) {
                hidden++;
                return;
            }

            let rowData = [];
            for (const [key, column] of Object.entries(columns)) {
                let value = getValue(sortedEntries[rowIndex], key);
                if (typeof column.render === 'function') {
                    value = column.render(sortedEntries[rowIndex], value);
                }

                rowData.push(<TableCell key={"column-" + key} className={clsx(column.alignment === "center" && classes.columnCenter)}>
                    { value }
                </TableCell>);
            }

            rows.push(
                <TableRow key={"entry-" + rowIndex}
                          className={clsx(hasClickHandler && classes.clickable)}
                          onClick={() => hasClickHandler && onClick(sortedEntries[rowIndex])}>
                    { rowData }
                </TableRow>
            );
        });

        if (hidden > 0) {
            rows.push(<TableRow key={"row-hidden"}>
                <TableCell colSpan={numColumns} className={classes.hidden}>
                    { "(" + (hidden > 1
                        ? sprintf(L("%d rows hidden due to filter"), hidden)
                        : L("1 rows hidden due to filter")) + ")"
                    }
                </TableCell>
            </TableRow>);
        } else if (rows.length === 0 && placeholder) {
            rows.push(<TableRow key={"row-placeholder"}>
                <TableCell colSpan={numColumns} className={classes.hidden}>
                    { placeholder }
                </TableCell>
            </TableRow>);
        }
    } else if (columns && data === null) {
        rows.push(<TableRow key={"loading"}>
            <TableCell colSpan={numColumns} className={classes.columnCenter}>
                <Grid container alignItems={"center"} spacing={1} justifyContent={"center"}>
                    <Grid item>{L("Loading")}…</Grid>
                    <Grid item><CircularProgress size={15}/></Grid>
                </Grid>
            </TableCell>
        </TableRow>)
    }
     */

    return <Box position={"relative"}>
            <Table className={clsx("data-table", className)} size="small" {...other}>
                <TableHead>
                    <TableRow>
                        { headerRow }
                    </TableRow>
                </TableHead>
                <TableBody>
                    { rows }
                </TableBody>
            </Table>
        {pagination.renderPagination(L, rows.length)}
    </Box>
}

export class DataColumn {
    constructor(label, field = null, sortable = true) {
        this.label = label;
        this.field = field;
        this.sortable = sortable;
    }

    compare(a, b) {
        throw new Error("Not implemented: compare");
    }

    renderData(entry) {
        return entry[this.field]
    }

    renderHead() {
        return this.label;
    }
}

export class StringColumn extends DataColumn {
    constructor(label, field = null, sortable = true, caseSensitive = false) {
        super(label, field, sortable);
        this.caseSensitve = caseSensitive;
    }

    compare(a, b) {
        if (this.caseSensitve) {
            return a.toString().localeCompare(b.toString());
        } else {
            return a.toString().toLowerCase().localeCompare(b.toString().toLowerCase());
        }
    }
}

export class NumericColumn extends DataColumn {
    constructor(label, field = null, sortable = true) {
        super(label, field, sortable);
    }

    compare(a, b) {
        return a - b;
    }
}

export class DateTimeColumn extends DataColumn {
    constructor(label, field = null, sortable = true, format = "YYYY-MM-dd HH:mm:ss") {
        super(label, field, sortable);
        this.format = format;
    }

    compare(a, b) {
        if (typeof a === 'string') {
            a = parse(a, this.format, new Date()).getTime();
        }

        if (typeof b === 'string') {
            b = parse(b, this.format, new Date()).getTime();
        }

        return a - b;
    }
}
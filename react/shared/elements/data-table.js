import {Table, TableBody, TableCell, TableHead, TableRow} from "@material-ui/core";
import ArrowUpwardIcon from "@material-ui/icons/ArrowUpward";
import ArrowDownwardIcon from "@material-ui/icons/ArrowDownward";
import React, {useCallback, useContext, useEffect, useState} from "react";
import "./data-table.css";
import {LocaleContext} from "../locale";
import clsx from "clsx";
import {Box, IconButton} from "@mui/material";
import {formatDateTime} from "../util";
import CachedIcon from "@material-ui/icons/Cached";


export function DataTable(props) {

    const { className, placeholder,
        columns, data, pagination,
        fetchData, onClick, onFilter,
        defaultSortColumn, defaultSortOrder,
        title, ...other } = props;

    const {translate: L} = useContext(LocaleContext);

    const [doFetchData, setFetchData] = useState(false);
    const [sortAscending, setSortAscending] = useState(["asc","ascending"].includes(defaultSortOrder?.toLowerCase));
    const [sortColumn, setSortColumn] = useState(defaultSortColumn || null);
    const sortable = props.hasOwnProperty("sortable") ? !!props.sortable : true;
    const onRowClick = onClick || (() => {});

    const onFetchData = useCallback((force = false) => {
        if (doFetchData || force) {
            setFetchData(false);
            const orderBy = columns[sortColumn]?.field || null;
            const sortOrder = sortAscending ? "asc" : "desc";
            fetchData(pagination.getPage(), pagination.getPageSize(), orderBy, sortOrder);
        }
    }, [doFetchData, columns, sortColumn, sortAscending, pagination]);

    // pagination changed?
    useEffect(() => {
        let forceFetch = false;
        if (pagination.getPageSize() < pagination.getTotal()) {
            // page size is smaller than the total count
            forceFetch = true;
        } else if (data?.length && pagination.getPageSize() >= data.length && data.length < pagination.getTotal()) {
            // page size is greater than the current visible count but there were hidden rows before
            forceFetch = true;
        }

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
            headerRow.push(<TableCell key={"col-" + index} className={"data-table-clickable"}
                                      title={L("general.sort_by") + ": " + column.label}
                                      onClick={() => onChangeSort(index, column)}
                                      align={column.align}>
                {sortColumn === index ? (sortAscending ? <ArrowUpwardIcon /> : <ArrowDownwardIcon />): <></>}{column.renderHead(index)}
            </TableCell>);
        } else {
            headerRow.push(<TableCell key={"col-" + index} align={column.align}>
                {column.renderHead(index)}
            </TableCell>);
        }
    }

    const numColumns = columns.length;
    let rows = [];
    if (data && data?.length) {
        for (const [rowIndex, entry] of data.entries()) {
            let row = [];
            for (const [index, column] of columns.entries()) {
                row.push(<TableCell key={"col-" + index} align={column.align}>
                    {column.renderData(L, entry, index)}
                </TableCell>);
            }

            rows.push(<TableRow className={clsx({["data-table-clickable"]: typeof onClick === 'function'})}
                                onClick={() => onRowClick(rowIndex, entry)}
                                key={"row-" + rowIndex}>
                { row }
            </TableRow>);
        }
    } else if (placeholder) {
        rows.push(<TableRow key={"row-placeholder"}>
            <TableCell colSpan={numColumns}>
                { placeholder }
            </TableCell>
        </TableRow>);
    }

    return <Box position={"relative"}>
            <h3>
                <IconButton onClick={() => onFetchData(true)}>
                    <CachedIcon/>
                </IconButton>
                {title}
            </h3>
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
    constructor(label, field = null, params = {}) {
        this.label = label;
        this.field = field;
        this.sortable = !params.hasOwnProperty("sortable") || !!params.sortable;
        this.align = params.align || "left";
        this.params = params;
    }

    renderData(L, entry, index) {
        return entry[this.field]
    }

    renderHead() {
        return this.label;
    }
}

export class StringColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
    }

    renderData(L, entry, index) {
        let data = super.renderData(L, entry, index);

        if (this.params.style) {
            data = <span style={this.params.style}>{data}</span>
        }

        return data;
    }
}

export class NumericColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
        this.decimalDigits = params.decimalDigits || null;
        this.integerDigits = params.integerDigits || null;
        this.prefix = params.prefix || "";
        this.suffix = params.suffix || "";
        this.decimalChar = params.decimalChar || ".";
    }

    renderData(L, entry, index) {
        let number = super.renderData(L, entry).toString();

        if (this.decimalDigits !== null) {
            number = number.toFixed(this.decimalDigits);
        }

        if (this.integerDigits !== null) {
            let currentLength = number.split(".")[0].length;
            if (currentLength < this.integerDigits) {
                number = number.padStart(this.integerDigits - currentLength, "0");
            }
        }

        if (this.decimalChar !== ".") {
            number = number.replace(".", this.decimalChar);
        }

        return this.prefix + number + this.suffix;
    }
}

export class DateTimeColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
        this.precise = !!params.precise;
    }

    renderData(L, entry, index) {
        let date = super.renderData(L, entry);
        return formatDateTime(L, date, this.precise);
    }
}

export class BoolColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
    }

    renderData(L, entry, index) {
        let data = super.renderData(L, entry);
        return L(data ? "general.yes" : "general.no");
    }
}

export class ControlsColumn extends DataColumn {
    constructor(label, buttons = [], params = {}) {
        super(label, null, { align: "center", ...params, sortable: false });
        this.buttons = buttons;
    }

    renderData(L, entry, index) {
        let buttonElements = [];
        for (const [index, button] of this.buttons.entries()) {
            let element = button.element;
            let props = {
                key: "button-" + index,
                onClick: (() => button.onClick(entry)),
                className: "data-table-clickable"
            }

            if (typeof button.showIf !== 'function' || button.showIf(entry)) {
                buttonElements.push(React.createElement(element, props))
            }
        }

        return <>
            {buttonElements}
        </>
    }
}
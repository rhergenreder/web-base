import React, {useCallback, useContext, useEffect, useState} from "react";
import "./data-table.css";
import {LocaleContext} from "../locale";
import clsx from "clsx";
import {Box, Button, Select, TextField, Table, TableBody, TableCell, TableHead, TableRow} from "@mui/material";
import {formatDate, formatDateTime} from "../util";
import {isNumber} from "chart.js/helpers";
import {ArrowUpward, ArrowDownward, Refresh} from "@mui/icons-material";
import TableBodyStriped from "./table-body-striped";


export function DataTable(props) {

    const { className, placeholder,
        columns, data, pagination,
        fetchData, onClick, onFilter,
        defaultSortColumn, defaultSortOrder,
        forceReload,
        buttons,
        ...other } = props;

    const {translate: L} = useContext(LocaleContext);

    const [doFetchData, setFetchData] = useState(false);
    const [sortAscending, setSortAscending] = useState(["asc","ascending"].includes(defaultSortOrder?.toLowerCase()));
    const [sortColumn, setSortColumn] = useState(isNumber(defaultSortColumn) || null);
    const sortable = !!fetchData && (props.hasOwnProperty("sortable") ? !!props.sortable : true);
    const onRowClick = onClick || (() => {});

    const onFetchData = useCallback((force = false) => {
        if (fetchData) {
            if (doFetchData || force) {
                setFetchData(false);
                const orderBy = columns[sortColumn]?.field || null;
                const sortOrder = sortAscending ? "asc" : "desc";
                fetchData(pagination.getPage(), pagination.getPageSize(), orderBy, sortOrder);
            }
        }
    }, [fetchData, doFetchData, columns, sortColumn, sortAscending, pagination]);

    // pagination changed?
    useEffect(() => {
        if (pagination) {
            let forceFetch = false;
            if (pagination.getPageSize() < pagination.getTotal()) {
                // page size is smaller than the total count
                forceFetch = true;
            } else if (data?.length && pagination.getPageSize() >= data.length && data.length < pagination.getTotal()) {
                // page size is greater than the current visible count but there were hidden rows before
                forceFetch = true;
            }

            onFetchData(forceFetch);
        }
    }, [pagination?.data?.pageSize, pagination?.data?.current]);

    // sorting changed or we forced an update
    useEffect(() => {
        onFetchData(true);
    }, [sortAscending, sortColumn, forceReload]);

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
        } else if (column.hidden) {
            continue;
        }

        if (sortable && column.sortable) {
            headerRow.push(<TableCell key={"col-" + index} className={"data-table-clickable"}
                                      title={L("general.sort_by") + ": " + column.label}
                                      onClick={() => onChangeSort(index, column)}
                                      align={column.align}>
                {sortColumn === index ?
                    (sortAscending ? <ArrowUpward /> : <ArrowDownward />) :
                    <></>
                }
                {column.renderHead(index)}
            </TableCell>);
        } else {
            headerRow.push(<TableCell key={"col-" + index} align={column.align}>
                {column.renderHead(index)}
            </TableCell>);
        }
    }

    const numColumns = columns.length;
    let numRows = 0;
    let rows = [];
    if (data && data?.length) {
        numRows = data.length;
        for (const [rowIndex, entry] of data.entries()) {
            let row = [];
            for (const [index, column] of columns.entries()) {
                row.push(<TableCell key={"col-" + index} align={column.align}>
                    {column.renderData(L, entry, index)}
                </TableCell>);
            }

            rows.push(<TableRow className={clsx({["data-table-clickable"]: typeof onClick === 'function'})}
                                onClick={(e) => ["tr","td"].includes(e.target.tagName.toLowerCase()) && onRowClick(rowIndex, entry)}
                                key={"row-" + rowIndex}>
                { row }
            </TableRow>);
        }
    } else if (placeholder) {
        rows.push(<TableRow key={"row-placeholder"}>
            <TableCell colSpan={numColumns} align={"center"}>
                { placeholder }
            </TableCell>
        </TableRow>);
    }

    return <Box position={"relative"}>
            <Box textAlign={"left"} mb={1} className={"data-table-button-bar"}>
                <Button startIcon={<Refresh />} size={"small"} variant={"outlined"}
                    onClick={() => onFetchData(true)}>
                    {L("general.reload")}
                </Button>
                {(buttons || []).map(b => <Button size={"small"} variant={"outlined"} {...b} />)}
            </Box>
            <Table className={clsx("data-table", className)} size="small" {...other}>
                <TableHead>
                    <TableRow>
                        { headerRow }
                    </TableRow>
                </TableHead>
                <TableBodyStriped>
                    { rows }
                </TableBodyStriped>
            </Table>
        {pagination && pagination.renderPagination(L, numRows)}
    </Box>
}

export class DataColumn {
    constructor(label, field = null, params = {}) {
        this.label = label;
        this.field = field;
        this.sortable = !params.hasOwnProperty("sortable") || !!params.sortable;
        this.align = params.align || "left";
        this.hidden = !!params.hidden;
        this.params = params;
    }

    renderData(L, entry, index) {
        return typeof this.field === 'function' ? this.field(entry) : entry[this.field];
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

        if (this.params.maxLength && data?.length && data.length > this.params.maxLength) {
            data = data.substring(0, this.params.maxLength) + "...";
        }

        if (this.params.style) {
            let style = (typeof this.params.style === 'function'
                ? this.params.style(entry) : this.params.style);
            data = <span style={style}>{data}</span>
        }

        return data;
    }
}

export class ArrayColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
        this.seperator = params.seperator || ", ";
    }

    renderData(L, entry, index) {
        let data = super.renderData(L, entry, index);

        if (!Array.isArray(data)) {
            data = Object.values(data);
        }

        data = data.join(this.seperator);

        if (this.params.style) {
            let style = (typeof this.params.style === 'function'
                ? this.params.style(entry) : this.params.style);
            data = <span style={style}>{data}</span>
        }

        return data;
    }
}

export class SecretsColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
        this.asteriskCount = params.asteriskCount || 8;
        this.character = params.character || "*";
        this.canCopy = params.hasOwnProperty("canCopy") ? params.canCopy : true;
    }

    renderData(L, entry, index) {
        let originalData = super.renderData(L, entry, index);
        if (!originalData) {
            return "(None)";
        }

        let properties = this.params.properties || {};
        properties.className = clsx(properties.className, "font-monospace");

        if (this.canCopy) {
            properties.title = L("general.click_to_copy");
            properties.className = clsx(properties.className, "data-table-clickable");
            properties.onClick = () => {
                navigator.clipboard.writeText(originalData);
            };
        }

        return <span {...properties}>{this.character.repeat(this.asteriskCount)}</span>
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
        let number = super.renderData(L, entry, index).toString();

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
        let date = super.renderData(L, entry, index);
        return date ? formatDateTime(L, date, this.precise) : "";
    }
}

export class DateColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
    }

    renderData(L, entry, index) {
        let date = super.renderData(L, entry, index);
        return date ? formatDate(L, date) : "";
    }
}

export class BoolColumn extends DataColumn {
    constructor(label, field = null, params = {}) {
        super(label, field, params);
    }

    renderData(L, entry, index) {
        let data = super.renderData(L, entry, index);
        return L(data ? "general.yes" : "general.no");
    }
}

export class InputColumn extends DataColumn {
    constructor(label, field, type, onChange, params = {}) {
        super(label, field, { ...params, sortable: false });
        this.type = type;
        this.onChange = onChange;
        this.props = params.props || {};
    }

    renderData(L, entry, index) {
        let value = super.renderData(L, entry, index);
        let inputProps = typeof this.props === 'function' ? this.props(entry, index) : this.props;
        if (this.type === 'text') {
            return <TextField {...inputProps} size={"small"} fullWidth={true}
                              value={value} onChange={(e) => this.onChange(entry, index, e.target.value)} />
        } else if (this.type === "select") {
            let options = Object.entries(this.params.options || {}).map(([value, label]) =>
                <option key={"option-" + value} value={value}>{label}</option>);
            return <Select native {...inputProps} size={"small"} fullWidth={true} value={value}
                           onChange={(e) => this.onChange(entry, index, e.target.value)}>
                {options}
            </Select>
        }

        return <>[Invalid type: {this.type}]</>
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
            let element = typeof button.element === 'function'
                ? button.element(entry, index)
                : button.element;

            let buttonProps = {};
            if (typeof button.props === 'function') {
                buttonProps = button.props(entry, index);
            } else {
                buttonProps = button.props;
            }

            let props = {
                ...buttonProps,
                key: "button-" + index,
            }

            if (button.hasOwnProperty("disabled")) {
                props.disabled = typeof button.disabled === 'function'
                    ? button.disabled(entry, index)
                    : button.disabled;
            }

            if (!props.disabled) {
                props.onClick = (e) => { e.stopPropagation(); button.onClick(entry, index); }
            }

            if ((!button.hasOwnProperty("hidden")) ||
                (typeof button.hidden === 'function' && !button.hidden(entry, index)) ||
                (!button.hidden)) {
                buttonElements.push(React.createElement(element, props))
            }
        }

        return <Box className={"data-table-buttons"}>
            {buttonElements}
        </Box>
    }
}
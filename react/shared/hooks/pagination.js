import React, {useState} from "react";
import {Box, MenuItem, Select, Pagination as MuiPagination} from "@mui/material";
import {sprintf} from "sprintf-js";
import {FormControl} from "@material-ui/core";

class Pagination {

    constructor(data, setData) {
        this.data = data;
        this.setData = setData;
    }

    getPage() {
        return this.data.current;
    }

    getPageSize() {
        return this.data.pageSize;
    }

    setPage(page) {
        this.setData({...this.data, current: page});
    }

    setPageSize(pageSize) {
        this.setData({...this.data, pageSize: pageSize});
    }

    setTotal(count) {
        this.setData({...this.data, total: count});
    }

    reset() {
        this.setData({current: 1, pageSize: 25, total: 0});
    }

    getPageCount() {
        if (this.data.pageSize && this.data.total) {
            return Math.max(1, Math.ceil(this.data.total / this.data.pageSize));
        } else {
            return 1;
        }
    }

    getParams() {
        return [this.data.current, this.data.pageSize];
    }

    getTotal() {
        return this.data.total;
    }

    update(data) {
        this.setData(data);
    }

    renderPagination(L, numEntries, options = null) {
        options = options || [10, 25, 50, 100];

        return <Box display={"grid"} gridTemplateColumns={"75px auto"} className={"pagination-controls"}>
            <FormControl>
                <Select
                    native
                    value={this.data.pageSize}
                    className={"pagination-page-size"}
                    label={L("general.entries_per_page")}
                    onChange={(e) => this.setPageSize(parseInt(e.target.value))}
                    size={"small"}
                >
                    {options.map(size => <option key={"size-" + size} value={size}>{size}</option>)}
                </Select>
            </FormControl>
            <MuiPagination
                count={this.getPageCount()}
                onChange={(_, page) => this.setPage(page)}
            />
            <Box gridColumn={"1 / 3"} mt={1}>
                {sprintf(L("general.showing_x_of_y_entries"), numEntries, this.data.total)}
            </Box>
        </Box>
    }
}

export default function usePagination() {

    const [pagination, setPagination] = useState({
        current: 1, pageSize: 25, total: 0
    });

    return new Pagination(pagination, setPagination);
}
import React, {useState} from "react";
import {Box, MenuItem, Select, Pagination as MuiPagination} from "@mui/material";
import {sprintf} from "sprintf-js";

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

        return <Box>
            <Select
                value={this.data.pageSize}
                label={L("general.entries_per_page")}
                onChange={(e) => this.setPageSize(parseInt(e.target.value))}
                size={"small"}
            >
                {options.map(size => <MenuItem key={"size-" + size} value={size}>{size}</MenuItem>)}
            </Select>
            <MuiPagination count={this.getPageCount()} onChange={(_, page) => this.setPage(page)} />
            {sprintf(L("general.showing_x_of_y_entries"), numEntries, this.data.total)}
        </Box>
    }
}

export default function usePagination() {

    const [pagination, setPagination] = useState({
        current: 1, pageSize: 25, total: 0
    });

    return new Pagination(pagination, setPagination);
}
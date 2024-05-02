import {styled, TableBody} from "@mui/material";

const TableBodyStriped = styled(TableBody)(({ theme }) => ({
    '& tr:nth-of-type(odd)': {
        backgroundColor: theme.palette.grey[0],
    },
    '& tr:nth-of-type(even)': {
        backgroundColor: theme.palette.grey[100],
    },
}));

export default TableBodyStriped;
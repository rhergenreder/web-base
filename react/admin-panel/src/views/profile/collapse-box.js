import {Box, Collapse, FormControl, FormGroup, FormLabel, Paper, styled, TextField} from "@mui/material";
import {ExpandLess, ExpandMore} from "@mui/icons-material";

const StyledBox = styled(Box)((props) => ({
    "& > header": {
        display: "grid",
        gridTemplateColumns: "50px 50px auto",
        cursor: "pointer",
        marginTop: props.theme.spacing(1),
        padding: props.theme.spacing(1),
        "& > svg": {
            justifySelf: "center",
        },
        "& > h5": {
            margin: 0
        }
    },
    "& > div:nth-of-type(1)": {
        padding: props.theme.spacing(2),
        borderTopWidth: 1,
        borderTopColor: props.theme.palette.divider,
        borderTopStyle: "solid"
    }
}));

export default function CollapseBox(props) {
    const {open, title, icon, children, onToggle, ...other} = props;

    return <StyledBox component={Paper} {...other}>
        <header onClick={onToggle}>
            { open ? <ExpandLess/> : <ExpandMore /> }
            { icon }
            <h5>{title}</h5>
        </header>
        <Collapse in={open} timeout={"auto"} unmountOnExit>
            {children}
        </Collapse>
    </StyledBox>
}
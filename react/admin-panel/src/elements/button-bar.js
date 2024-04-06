import {Box, styled} from "@mui/material";

const ButtonBar = styled(Box)((props) => ({
    "& > button, & > label": {
        marginRight: props.theme.spacing(1)
    }
}));

export default ButtonBar;
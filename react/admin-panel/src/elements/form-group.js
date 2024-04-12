import {FormGroup, styled} from "@mui/material";

const SpacedFormGroup = styled(FormGroup)((props) =>  ({
    marginBottom: props.theme.spacing(2)
}));

export default SpacedFormGroup;
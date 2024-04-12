import SpacedFormGroup from "../../elements/form-group";
import {FormControl, FormLabel, TextField} from "@mui/material";
import {useContext} from "react";
import {LocaleContext} from "shared/locale";

export default function SettingsTextInput(props) {

    const {key_name, value, onChangeValue, disabled, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    return <SpacedFormGroup {...other}>
        <FormLabel disabled={!!disabled}>{L("settings." + key_name)}</FormLabel>
        <FormControl>
            <TextField size={"small"} variant={"outlined"}
                       disabled={!!disabled}
                       value={value}
                       onChange={e => onChangeValue(e.target.value)} />
        </FormControl>
    </SpacedFormGroup>
}
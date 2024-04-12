import {FormControl, FormLabel, TextField} from "@mui/material";
import SpacedFormGroup from "../../elements/form-group";
import {useContext} from "react";
import {LocaleContext} from "shared/locale";

export default function SettingsNumberInput(props) {

    const {key_name, value, minValue, maxValue, onChangeValue, disabled, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    return <SpacedFormGroup {...other}>
        <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
        <FormControl>
            <TextField size={"small"} variant={"outlined"}
                       type={"number"}
                       disabled={disabled}
                       inputProps={{min: minValue, max: maxValue}}
                       value={value}
                       onChange={e => onChangeValue(e.target.value)} />
        </FormControl>
    </SpacedFormGroup>
}
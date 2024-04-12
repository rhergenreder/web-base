import {FormControl, FormLabel, Select} from "@mui/material";
import SpacedFormGroup from "../../elements/form-group";
import {useContext} from "react";
import {LocaleContext} from "shared/locale";

export default function SettingsSelection(props) {

    const {key_name, value, options, onChangeValue, disabled, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    return <SpacedFormGroup {...other}>
        <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
        <FormControl>
            <Select native value={value}
                    disabled={disabled}
                    size={"small"} onChange={e => onChangeValue(e.target.value)}>
                {options.map(option => <option
                    key={"option-" + option}
                    value={option}>
                    {option}
                </option>)}
            </Select>
        </FormControl>
    </SpacedFormGroup>
}
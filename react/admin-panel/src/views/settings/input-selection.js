import {FormControl, FormLabel, Select} from "@mui/material";
import SpacedFormGroup from "../../elements/form-group";
import {useContext} from "react";
import {LocaleContext} from "shared/locale";

export default function SettingsSelection(props) {

    const {key_name, value, options, onChangeValue, disabled, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    let optionElements = [];
    if (Array.isArray(options)) {
        optionElements = options.map(option => <option
            key={"option-" + option}
            value={option}>
            {option}
        </option>);
    } else {
        optionElements = Object.entries(options).map(([value, label]) => <option
            key={"option-" + value}
            value={value}>
            {label}
        </option>);
    }
    
    return <SpacedFormGroup {...other}>
        <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
        <FormControl>
            <Select native value={value}
                    disabled={disabled}
                    size={"small"} onChange={e => onChangeValue(e.target.value)}>
                {optionElements}
            </Select>
        </FormControl>
    </SpacedFormGroup>
}
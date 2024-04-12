import {Checkbox, FormControlLabel} from "@mui/material";
import SpacedFormGroup from "../../elements/form-group";
import {parseBool} from "shared/util";
import {useContext} from "react";
import {LocaleContext} from "shared/locale";

export default function SettingsCheckBox(props) {

    const {key_name, value, onChangeValue, disabled, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    return <SpacedFormGroup {...other}>
        <FormControlLabel
            disabled={disabled}
            control={<Checkbox
                disabled={disabled}
                checked={parseBool(value)}
                onChange={(e, v) => onChangeValue(v)} />}
            label={L("settings." + key_name)} />
    </SpacedFormGroup>
}
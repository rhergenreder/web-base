import {Autocomplete, Chip, FormLabel, TextField} from "@mui/material";
import SpacedFormGroup from "../../elements/form-group";
import {useCallback, useContext, useState} from "react";
import {LocaleContext} from "shared/locale";

export default function SettingsTextValues(props) {

    const {key_name, value, options, onChangeValue, disabled, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    const [textInput, setTextInput] = useState("");

    const onFinishTyping = useCallback(() => {
        setTextInput("");
        const newValue = textInput?.trim();
        if (newValue) {
            onChangeValue(value ? [...value, newValue] : [newValue]);
        }
    }, [textInput, value]);

    return <SpacedFormGroup {...other}>
        <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
        <Autocomplete
            clearIcon={false}
            options={[]}
            freeSolo
            multiple
            value={value || []}
            inputValue={textInput}
            onChange={(e, v) => onChangeValue(v)}
            onInputChange={e => setTextInput(e.target.value.trim())}
            renderTags={(values, props) =>
                values.map((option, index) => (
                    <Chip label={option} {...props({ index })} />
                ))
            }
            renderInput={(params) => <TextField
                {...params}
                onKeyDown={e => {
                    if (["Enter", "Tab", ",", " "].includes(e.key)) {
                        e.preventDefault();
                        e.stopPropagation();
                        onFinishTyping();
                    }
                }}
                onBlur={onFinishTyping} />}
        />
    </SpacedFormGroup>
}
import React, {useEffect, useState} from "react";
import {
    Box,
    Button,
    Dialog as MuiDialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    Input, TextField
} from "@mui/material";

export default function Dialog(props) {

    const show = props.show;
    const onClose = props.onClose || function() { };
    const onOption = props.onOption || function() { };
    const options = props.options || ["Close"];
    const inputs = props.inputs || [];

    const [inputData, setInputData] = useState({});

    useEffect(() => {
        if (props.inputs) {
            let initialData = {};
            for (const input of props.inputs) {
                initialData[input.name] = input.value || "";
            }
            setInputData(initialData);
        }
    }, [props.inputs]);

    let buttons = [];
    for (const [index, name] of options.entries()) {
        buttons.push(
            <Button variant={"outlined"} size={"small"} key={"button-" + name}
                    onClick={() => { onClose(); onOption(index, inputData); setInputData({}); }}>
                {name}
            </Button>
        )
    }

    let inputElements = [];
    for (const input of inputs) {
        let inputProps = { ...input };
        delete inputProps.name;
        delete inputProps.type;

        switch (input.type) {
            case 'text':
                inputElements.push(<TextField
                    {...inputProps}
                    sx={{marginTop: 1}}
                    size={"small"} fullWidth={true}
                    key={"input-" + input.name}
                    value={inputData[input.name] || ""}
                    onChange={e => setInputData({ ...inputData, [input.name]: e.target.value })}
                />)
                break;
        }
    }

    return <MuiDialog
        open={show}
        onClose={onClose}>
        <DialogTitle>
            { props.title }
        </DialogTitle>
        <DialogContent>
            <DialogContentText>
                { props.message }
            </DialogContentText>
            <Box mt={2}>
                { inputElements }
            </Box>
        </DialogContent>
        <DialogActions>
            { buttons }
        </DialogActions>
    </MuiDialog>
}
import React, {useEffect, useState} from "react";
import {
    Box,
    Button,
    Dialog as MuiDialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    List, ListItem, TextField
} from "@mui/material";

export default function Dialog(props) {

    const show = !!props.show;
    const onClose = props.onClose || function() { };
    const onOption = props.onOption || function() { };
    const options = props.options || ["Close"];
    const inputs = props.inputs || [];

    const [inputData, setInputData] = useState({});

    useEffect(() => {
        if (props.inputs) {
            let initialData = {};
            for (const input of props.inputs) {
                if (input.type !== "label" && input.hasOwnProperty("name")) {
                    initialData[input.name] = input.value || "";
                }
            }
            setInputData(initialData);
        }
    }, [props.inputs]);

    let buttons = [];
    for (const [index, name] of options.entries()) {
        buttons.push(
            <Button variant={"outlined"} size={"small"} key={"button-" + name}
                    onClick={() => {
                        let res = onOption(index, inputData);
                        if (res || res === undefined) {
                            onClose();
                            setInputData({});
                        }
                    }}>
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
            case 'label':
                delete inputProps.value;
                inputElements.push(<span {...inputProps}>{input.value}</span>);
                break;
            case 'text':
            case 'number':
            case 'password': {
                let onChange = (input.type === "number") ?
                    e => setInputData({ ...inputData, [input.name]: e.target.value.replace(/[^0-9,.]/, '') }) :
                    e => setInputData({ ...inputData, [input.name]: e.target.value });

                inputElements.push(<TextField
                    {...inputProps}
                    type={input.type === "number" ? "text" : input.type}
                    size={"small"} fullWidth={true}
                    key={"input-" + input.name}
                    value={inputData[input.name] || ""}
                    onChange={onChange}
                />)
            } break;
            case 'list':
                delete inputProps.items;
                let listItems = input.items.map((item, index) => <ListItem key={"item-" + index}>{item}</ListItem>);
                inputElements.push(<Box
                    {...inputProps}
                    sx={{marginTop: 1}}
                    key={"input-" + input.name}
                >
                    <List>
                        {listItems}
                    </List>
                </Box>);
                break;
            case 'custom':
                let element = inputProps.element;
                delete inputProps.element;
                inputElements.push(React.createElement(element, inputProps));
                break;
            default:
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
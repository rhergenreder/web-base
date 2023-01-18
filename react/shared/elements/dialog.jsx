import React, {useContext} from "react";
import {Dialog as MuiDialog,  DialogActions, DialogContent, DialogContentText, DialogTitle} from "@mui/material";
import {Button} from "@material-ui/core";
import {LocaleContext} from "../locale";
import "./dialog.css";

export default function Dialog(props) {

    const show = props.show;
    const onClose = props.onClose || function() { };
    const onOption = props.onOption || function() { };
    const options = props.options || ["Close"];
    const type = props.type || "default";
    const {translate: L} = useContext(LocaleContext);


    let buttons = [];
    for (let name of options) {
        let type = "default";
        if (name === "Yes") type = "warning";
        else if(name === "No") type = "danger";

        buttons.push(
            <Button variant={"outlined"} size={"small"} type="button" key={"button-" + name}
                    data-dismiss={"modal"} onClick={() => { onClose(); onOption(name); }}>
                {name}
            </Button>
        )
    }

    return <MuiDialog
        open={show}
        onClose={onClose}
        aria-labelledby="alert-dialog-title"
        aria-describedby="alert-dialog-description">
        <DialogTitle>{ props.title }</DialogTitle>
        <DialogContent>
            <DialogContentText>
                { props.message }
            </DialogContentText>
        </DialogContent>
        <DialogActions>
            {buttons}
        </DialogActions>
    </MuiDialog>
}
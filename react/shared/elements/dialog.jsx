import React from "react";
import clsx from "clsx";
import {Box, Modal} from "@mui/material";
import {Button, Typography} from "@material-ui/core";
import "./dialog.css";

export default function Dialog(props) {

    const show = props.show;
    const onClose = props.onClose || function() { };
    const onOption = props.onOption || function() { };
    const options = props.options || ["Close"];
    const type = props.type || "default";

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

    return <Modal
        open={show}
        onClose={onClose}
        aria-labelledby="modal-title"
        aria-describedby="modal-description"
    >
        <Box className={clsx("modal-dialog", props.className)}>
            <Typography id="modal-title" variant="h6" component="h2">
                {props.title}
            </Typography>
            <Typography id="modal-description" sx={{ mt: 2 }}>
                {props.message}
            </Typography>
            { buttons }
        </Box>
    </Modal>
}
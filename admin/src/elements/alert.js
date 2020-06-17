import Icon from "./icon";
import React from "react";


export default function Alert(props) {

    const onClose = props.onClose || function() { };
    const title = props.title || "Untitled Alert";
    const message = props.message || "Alert message";

    return (
        <div className={"alert alert-danger alert-dismissible"}>
            <button type="button" className={"close"} data-dismiss={"alert"} aria-hidden={"true"} onClick={onClose}>×</button>
            <h5><Icon icon={"ban"} className={"icon"} /> {title}</h5>
            {message}
        </div>
    )
}
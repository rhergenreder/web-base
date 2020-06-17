import Icon from "./icon";
import React from "react";


export default function Alert(props) {

    const onClose = props.onClose || function() { };
    const title = props.title || "Untitled Alert";
    const message = props.message || "Alert message";
    const type = props.type || "danger";

    let icon = "ban";
    if (type === "warning") {
        icon = "exclamation-triangle";
    } else if(type === "success") {
        icon = "check";
    }

    return (
        <div className={"alert alert-" + type + " alert-dismissible"}>
            <button type="button" className={"close"} data-dismiss={"alert"} aria-hidden={"true"} onClick={onClose}>×</button>
            <h5><Icon icon={icon} className={"icon"} /> {title}</h5>
            {message}
        </div>
    )
}
import * as React from "react";

export default function Icon(props) {

    let classes = props.classes || [];
    classes = Array.isArray(classes) ? classes : classes.toString().split(" ");
    let type = props.type || "fas";
    let icon = props.icon;

    classes.push("fa");
    classes.push(type + "-" + icon);

    if (icon === "spinner") {
        classes.push("fa-spin");
    }

    return (
        <i className={classes.join(" ")} />
    );
}
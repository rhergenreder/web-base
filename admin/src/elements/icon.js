import * as React from "react";

export default function Icon(props) {

    let classes = props.classes || [];
    classes = Array.isArray(classes) ? classes : classes.toString().split(" ");
    let type = props.type || "fas";
    let icon = props.icon;

    classes.push(type);
    classes.push("fa-" + icon);

    if (icon === "spinner") {
        classes.push("fa-spin");
    }

    let newProps = {...props, className: classes.join(" ") };
    delete newProps["classes"];
    delete newProps["type"];
    delete newProps["icon"];

    return (
        <i {...newProps} />
    );
}
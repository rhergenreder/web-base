import React from 'react';

export default function Icon(props) {

    let classes = props.className || [];
    classes = Array.isArray(classes) ? classes : classes.toString().split(" ");
    let type = props.type || "fas";
    let icon = props.icon;

    classes.push(type);
    classes.push("fa-" + icon);

    if (icon === "spinner" || icon === "circle-notch") {
        classes.push("fa-spin");
    }

    let newProps = {...props, className: classes.join(" ") };
    delete newProps["type"];
    delete newProps["icon"];

    return (
        <i {...newProps} />
    );
}
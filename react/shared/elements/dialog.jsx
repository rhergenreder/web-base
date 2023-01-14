import React from "react";
import clsx from "clsx";

export default function Dialog(props) {

    const show = props.show;
    const classes = ["modal", "fade"];
    const style = { paddingRight: "12px", display: (show ? "block" : "none") };
    const onClose = props.onClose || function() { };
    const onOption = props.onOption || function() { };
    const options = props.options || ["Close"];

    let buttons = [];
    for (let name of options) {
        let type = "default";
        if (name === "Yes") type = "warning";
        else if(name === "No") type = "danger";

        buttons.push(
            <button type="button" key={"button-" + name} className={"btn btn-" + type}
                    data-dismiss={"modal"} onClick={() => { onClose(); onOption(name); }}>
                {name}
            </button>
        )
    }

    return (
        <div className={clsx(classes, show && "show")} style={style} aria-modal={"true"} onClick={() => onClose()}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
                <div className="modal-content">
                    <div className="modal-header">
                        <h4 className="modal-title">{props.title}</h4>
                        <button type="button" className="close" data-dismiss="modal" aria-label="Close" onClick={() => onClose()}>
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div className="modal-body">
                        <p>{props.message}</p>
                    </div>
                    <div className="modal-footer">
                        { buttons }
                    </div>
                </div>
            </div>
        </div>
    );
}
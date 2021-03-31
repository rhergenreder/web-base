import React from 'react';

export function Popup(props) {

    let buttonNames = props.buttons || ["Ok", "Cancel"];
    let onClick     = props.onClick || function () { };
    let visible     = !!props.visible;
    let title       = props.title || "Popup Title";
    let onClose     = props.onClose || function() { };

    let buttons = [];
    const colors = ["primary", "secondary", "success", "warning", "danger"];
    for (let i = 0; i < buttonNames.length; i++) {
        let name = buttonNames[i];
        let color = colors[i % colors.length];
        buttons.push(
            <button key={"btn-" + i} type={"button"} className={"btn btn-" + color} data-dismiss={"modal"}
                    onClick={() => onClick(name)}>
                {name}
            </button>
        );
    }

    return <>
        <div className={"modal fade" + (visible ? " show" : "")} tabIndex="-1" role="dialog" style={{display: (visible) ? "block" : "none"}}>
            <div className="modal-dialog" role="document">
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">{title}</h5>
                        <button type="button" className="close" aria-label="Close" onClick={onClose}>
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div className="modal-body">
                        {props.children}
                    </div>
                    <div className="modal-footer">
                        {buttons}
                    </div>
                </div>
            </div>
        </div>
        {visible ? <div className={"modal-backdrop fade show"}/> : <></>}
    </>;

}
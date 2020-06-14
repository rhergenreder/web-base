import React, {useEffect, useState} from "react";

function useStateFromProp(initialValue) {
    const [value, setValue] = useState(initialValue);

    useEffect(() => setValue(initialValue), [initialValue]);

    return [value, setValue];
}

export default function Dialog(props) {

    const [value, setValue] = useStateFromProp(props);

    function onClose() {
        setValue({ });
    }

    const show = typeof value.message !== "undefined";
    const classes = "modal fade" + (show ? " show" : "");
    const style = { paddingRight: "12px", display: (show ? "block" : "none") };

    return (
        <div className={classes} id="modal-default" style={style} aria-modal="true" onClick={() => onClose()}>
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
                    <div className="modal-footer justify-content-between">
                        <button type="button" className="btn btn-default" data-dismiss="modal" onClick={() => onClose()}>Close</button>
                    </div>
                </div>
            </div>
        </div>
    );
}
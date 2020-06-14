import React from "react";

export default class Dialog extends React.Component {

    constructor(props) {
        super(props);
        this.state = { hidden: !!props.hidden };
    }

    onClose() {
        this.setState({ hidden: true });
    }

    render() {

        console.log("Rendering dialog with:", this.props);

        let classes = "modal fade";
        if (!this.state.hidden) {
            classes *= " show";
        }

        return <div className={classes} id="modal-default" style={{paddingRight: "12px"}} aria-modal="true" onClick={() => this.onClose()}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
                <div className="modal-content">
                    <div className="modal-header">
                        <h4 className="modal-title">{this.props.title}</h4>
                        <button type="button" className="close" data-dismiss="modal" aria-label="Close" onClick={() => this.onClose()}>
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div className="modal-body">
                        <p>{this.props.message}</p>
                    </div>
                    <div className="modal-footer justify-content-between">
                        <button type="button" className="btn btn-default" data-dismiss="modal" onClick={() => this.onClose()}>Close</button>
                    </div>
                </div>
            </div>
        </div>
    }
}
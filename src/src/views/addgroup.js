import Alert from "../elements/alert";
import {Link} from "react-router-dom";
import * as React from "react";
import Icon from "../elements/icon";
import ReactTooltip from "react-tooltip";

export default class CreateGroup extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            alerts: [],
            isSubmitting: false,
            name: "",
            color: "#123456"
        };

        this.parent = {
            api: props.api,
        };
    }

    removeAlert(i) {
        if (i >= 0 && i < this.state.alerts.length) {
            let alerts = this.state.alerts.slice();
            alerts.splice(i, 1);
            this.setState({...this.state, alerts: alerts});
        }
    }

    render() {

        let alerts = [];
        for (let i = 0; i < this.state.alerts.length; i++) {
            alerts.push(<Alert key={"error-" + i} onClose={() => this.removeAlert(i)} {...this.state.alerts[i]}/>)
        }

        return <>
            <div className="content-header">
                <div className="container-fluid">
                    <div className="row mb-2">
                        <div className="col-sm-6">
                            <h1 className="m-0 text-dark">Create a new user</h1>
                        </div>
                        <div className="col-sm-6">
                            <ol className="breadcrumb float-sm-right">
                                <li className="breadcrumb-item"><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item"><Link to={"/admin/users"}>Users</Link></li>
                                <li className="breadcrumb-item active">Add User</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                <div className={"row"}>
                    <div className={"col-lg-6 pl-5 pr-5"}>
                        {alerts}
                        <form role={"form"} onSubmit={(e) => this.submitForm(e)}>
                            <div className={"form-group"}>
                                <label htmlFor={"name"}>Group Name</label>
                                <input type={"text"} className={"form-control"} placeholder={"Name"}
                                       name={"name"} id={"name"} maxLength={32} value={this.state.name}
                                       onChange={this.onChangeInput.bind(this)}/>
                            </div>

                            {/* TODO: add color picker */}
                            <div className={"form-group"}>
                                <label htmlFor={"color"}>Color</label>
                                <input type={"text"} className={"form-control"} placeholder={"Color"}
                                       id={"color"} name={"color"} maxLength={64} value={this.state.color}
                                       onChange={this.onChangeInput.bind(this)}/>
                            </div>

                            <Link to={"/admin/users"} className={"btn btn-info mt-2 mr-2"}>
                                <Icon icon={"arrow-left"}/>
                                &nbsp;Back
                            </Link>
                            { this.state.isSubmitting
                                ? <button type={"submit"} className={"btn btn-primary mt-2"} disabled>Loading…&nbsp;<Icon icon={"circle-notch"} /></button>
                                : <button type={"submit"} className={"btn btn-primary mt-2"}>Submit</button>
                            }
                        </form>
                    </div>
                </div>
            </div>
            <ReactTooltip/>
        </>;
    }

    onChangeInput(event) {
        const target = event.target;
        const value = target.value;
        const name = target.name;
        this.setState({ ...this.state, [name]: value });
    }

    submitForm(e) {
        e.preventDefault();
        const name = this.state.name;
        const color = this.state.color;
        this.setState({ ...this.state, isSubmitting: true });
        this.parent.api.createGroup(name, color).then((res) => {
            let alerts = this.state.alerts.slice();
            if (res.success) {
                alerts.push({message: "Group was successfully created", title: "Success!", type: "success"});
                this.setState({ ...this.state, name: "", color: "", alerts: alerts, isSubmitting: false });
            } else {
                alerts.push({message: res.msg, title: "Error creating Group", type: "danger"});
                this.setState({ ...this.state, name: "", color: "", alerts: alerts, isSubmitting: false });
            }
        });
    }
}
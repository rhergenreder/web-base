import React from "react";
import {Link} from "react-router-dom";
import Alert from "../elements/alert";
import {Collapse} from "react-collapse/lib/Collapse";
import Icon from "../elements/icon";

export default class Settings extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            errors: [],
            settings: {},
            generalOpened: true,
            mailOpened: true,
            etcOpened : true
        };

        this.parent = {
            api: props.api
        }
    }

    componentDidMount() {
        this.parent.api.getSettings().then((res) => {
            if (res.success) {
                this.setState({...this.state, settings: res.settings });
            } else {
                let errors = this.state.errors.slice();
                errors.push({ title: "Error fetching settings", message: res.msg });
                this.setState({...this.state, errors: errors});
            }
        });
    }

    removeError(i) {
        if (i >= 0 && i < this.state.errors.length) {
            let errors = this.state.errors.slice();
            errors.splice(i, 1);
            this.setState({...this.state, errors: errors});
        }
    }

    toggleCollapse(key) {
        this.setState({ ...this.state, [key]: !this.state[key] });
    }

    render() {

        let errors = [];
        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.errors[i]}/>)
        }

        return <>
            <div className={"content-header"}>
                <div className={"container-fluid"}>
                    <div className={"row mb-2"}>
                        <div className="col-sm-6">
                            <h1 className="m-0 text-dark">Settings</h1>
                        </div>
                        <div className="col-sm-6">
                            <ol className="breadcrumb float-sm-right">
                                <li className="breadcrumb-item"><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item active">Settings</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                {errors}
                <div>
                    <div className={"card card-primary"}>
                        <div className={"card-header"} style={{cursor: "pointer"}} onClick={() => this.toggleCollapse("generalOpened")}>
                            <h4 className={"card-title"}>
                                <Icon className={"mr-2"} icon={"cogs"} />
                                General Settings
                            </h4>
                            <div className={"card-tools"}>
                                <span className={"btn btn-tool btn-sm"}>
                                    <Icon icon={ this.state.generalOpened ? "angle-up" : "angle-down" }/>
                                </span>
                            </div>
                        </div>
                        <Collapse isOpened={this.state.generalOpened}>
                            <div className={"card-body"}>
                                <div className={"row"}>
                                    <div className={"col-12 col-lg-6"}>
                                        <div className={"form-group"}>
                                            <label htmlFor={"site_name"}>Site Name</label>
                                            <input type={"text"} className={"form-control"} value={this.state.settings["site_name"] ?? ""}
                                                   placeholder={"Enter a title"} name={"site_name"} id={"site_name"} onChange={this.onChangeValue.bind(this)} />
                                        </div>
                                        <div className={"form-group"}>
                                            <label htmlFor={"base_url"}>Base URL</label>
                                            <input type={"text"} className={"form-control"} value={this.state.settings["base_url"] ?? ""}
                                                   placeholder={"Enter a url"} name={"base_url"} id={"base_url"} onChange={this.onChangeValue.bind(this)} />
                                        </div>
                                        <div className={"form-group"}>
                                            <label htmlFor={"user_registration_enabled"}>User Registration</label>
                                            <div className={"form-check"}>
                                                <input type={"checkbox"} className={"form-check-input"} name={"user_registration_enabled"} id={"user_registration_enabled"}
                                                       defaultChecked={(this.state.settings["user_registration_enabled"] ?? "0") === "1"}
                                                       onChange={this.onChangeValue.bind(this)} />
                                                <label className={"form-check-label"} htmlFor={"user_registration_enabled"}>Allow anyone to register an account</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Collapse>
                    </div>
                    <div className={"card card-warning"}>
                        <div className={"card-header"} style={{cursor: "pointer"}} onClick={() => this.toggleCollapse("mailOpened")}>
                            <h4 className={"card-title"}>
                                <Icon className={"mr-2"} icon={"envelope"} />
                                Mail Settings
                            </h4>
                            <div className={"card-tools"}>
                                <span className={"btn btn-tool btn-sm"}>
                                    <Icon icon={ this.state.generalOpened ? "angle-up" : "angle-down" }/>
                                </span>
                            </div>
                        </div>
                        <Collapse isOpened={this.state.mailOpened}>
                            <div className={"card-body"}>

                            </div>
                        </Collapse>
                    </div>
                    <div className={"card card-secondary"}>
                        <div className={"card-header"} style={{cursor: "pointer"}} onClick={() => this.toggleCollapse("etcOpened")}>
                            <h4 className={"card-title"}>
                                <Icon className={"mr-2"} icon={"stream"} />
                                Uncategorised
                            </h4>
                            <div className={"card-tools"}>
                                <span className={"btn btn-tool btn-sm"}>
                                    <Icon icon={ this.state.generalOpened ? "angle-up" : "angle-down" }/>
                                </span>
                            </div>
                        </div>
                        <Collapse isOpened={this.state.etcOpened}>
                            <div className={"card-body"}>

                            </div>
                        </Collapse>
                    </div>
                </div>
            </div>
        </>
    }

    onChangeValue(event) {
        const target = event.target;
        const name = target.name;
        const type  = target.type;
        let value = target.value;

        if (type === "checkbox") {
            value = event.target.checked ? "1" : "0";
        }

        this.setState({ ...this.state, user: { ...this.state.user, settings: { ...this.state.settings, [name]: value} } });
    }
}
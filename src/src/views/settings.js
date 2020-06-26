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
            mailErrors: [],
            generalErrors: [],
            etcErrors: [],
            settings: {},
            generalOpened: true,
            mailOpened: true,
            etcOpened: true,
            isResetting: false,
            isSaving: false,
            isSending: false,
            test_email: "",
            unsavedMailSettings: false
        };

        this.parent = {
            api: props.api
        };

        this.mailKeys = ["mail_enabled", "mail_host", "mail_port", "mail_username", "mail_password", "mail_from"];
        this.generalKeys = ["site_name", "base_url", "user_registration_enabled"];
    }

    componentDidMount() {
        this.parent.api.getSettings().then((res) => {
            if (res.success) {
                this.setState({...this.state, settings: res.settings});
            } else {
                let errors = this.state.errors.slice();
                errors.push({title: "Error fetching settings", message: res.msg});
                this.setState({...this.state, errors: errors});
            }
        });
    }

    removeError(key, i) {
        if (i >= 0 && i < this.state[key].length) {
            let errors = this.state[key].slice();
            errors.splice(i, 1);
            this.setState({...this.state, [key]: errors});
        }
    }

    toggleCollapse(key) {
        this.setState({...this.state, [key]: !this.state[key]});
    }

    getGeneralCard() {

        let errors = [];
        for (let i = 0; i < this.state.generalErrors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError("generalErrors", i)} {...this.state.generalErrors[i]}/>)
        }

        return <>
            <div className={"card-header"} style={{cursor: "pointer"}}
                 onClick={() => this.toggleCollapse("generalOpened")}>
                <h4 className={"card-title"}>
                    <Icon className={"mr-2"} icon={"cogs"}/>
                    General Settings
                </h4>
                <div className={"card-tools"}>
                    <span className={"btn btn-tool btn-sm"}>
                        <Icon icon={this.state.generalOpened ? "angle-up" : "angle-down"}/>
                    </span>
                </div>
            </div>
            <Collapse isOpened={this.state.generalOpened}>
                <div className={"card-body"}>
                    <div className={"row"}>
                        <div className={"col-12 col-lg-6"}>
                            {errors}
                            <div className={"form-group"}>
                                <label htmlFor={"site_name"}>Site Name</label>
                                <input type={"text"} className={"form-control"}
                                       value={this.state.settings["site_name"] ?? ""}
                                       placeholder={"Enter a title"} name={"site_name"} id={"site_name"}
                                       onChange={this.onChangeValue.bind(this)}/>
                            </div>
                            <div className={"form-group"}>
                                <label htmlFor={"base_url"}>Base URL</label>
                                <input type={"text"} className={"form-control"}
                                       value={this.state.settings["base_url"] ?? ""}
                                       placeholder={"Enter a url"} name={"base_url"} id={"base_url"}
                                       onChange={this.onChangeValue.bind(this)}/>
                            </div>
                            <div className={"form-group"}>
                                <label htmlFor={"user_registration_enabled"}>User Registration</label>
                                <div className={"form-check"}>
                                    <input type={"checkbox"} className={"form-check-input"}
                                           name={"user_registration_enabled"}
                                           id={"user_registration_enabled"}
                                           defaultChecked={(this.state.settings["user_registration_enabled"] ?? "0") === "1"}
                                           onChange={this.onChangeValue.bind(this)}/>
                                    <label className={"form-check-label"}
                                           htmlFor={"user_registration_enabled"}>
                                        Allow anyone to register an account
                                    </label>
                                </div>
                            </div>
                            <div>
                                <button className={"btn btn-secondary ml-2"} onClick={() => this.onReset("generalErrors", this.generalKeys)}
                                        disabled={this.state.isResetting || this.state.isSaving}>
                                    {this.state.isResetting ?
                                        <span>Resetting&nbsp;<Icon icon={"circle-notch"}/></span> : "Reset"}
                                </button>
                                <button className={"btn btn-success ml-2"} onClick={() => this.onSave("generalErrors", this.generalKeys)}
                                        disabled={this.state.isResetting || this.state.isSaving}>
                                    {this.state.isSaving ?
                                        <span>Saving&nbsp;<Icon icon={"circle-notch"}/></span> : "Save"}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Collapse>
        </>
    }

    getEmailCard() {

        let errors = [];
        for (let i = 0; i < this.state.mailErrors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError("mailErrors", i)} {...this.state.mailErrors[i]}/>)
        }

        return <>
            <div className={"card-header"} style={{cursor: "pointer"}}
                 onClick={() => this.toggleCollapse("mailOpened")}>
                <h4 className={"card-title"}>
                    <Icon className={"mr-2"} icon={"envelope"}/>
                    Mail Settings
                </h4>
                <div className={"card-tools"}>
                    <span className={"btn btn-tool btn-sm"}>
                        <Icon icon={this.state.mailOpened ? "angle-up" : "angle-down"}/>
                    </span>
                </div>
            </div>
            <Collapse isOpened={this.state.mailOpened}>
                <div className={"card-body"}>
                    <div className={"row"}>
                        <div className={"col-12 col-lg-6"}>
                            {errors}
                            <div className={"form-group mt-2"}>
                                <div className={"form-check"}>
                                    <input type={"checkbox"} className={"form-check-input"}
                                           name={"mail_enabled"} id={"mail_enabled"}
                                           checked={(this.state.settings["mail_enabled"] ?? "0") === "1"}
                                           onChange={this.onChangeValue.bind(this)}/>
                                    <label className={"form-check-label"} htmlFor={"mail_enabled"}>
                                        Enable E-Mail service
                                    </label>
                                </div>
                                <hr className={"m-3"}/>
                                <label htmlFor={"mail_username"}>Username</label>
                                <div className={"input-group"}>
                                    <div className={"input-group-prepend"}>
                                        <span className={"input-group-text"}>
                                            <Icon icon={"hashtag"}/>
                                        </span>
                                    </div>
                                    <input type={"text"} className={"form-control"}
                                           value={this.state.settings["mail_username"] ?? ""}
                                           placeholder={"Enter a username"} name={"mail_username"}
                                           id={"mail_username"} onChange={this.onChangeValue.bind(this)}
                                           disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1"}/>
                                </div>
                                <label htmlFor={"mail_password"} className={"mt-2"}>Password</label>
                                <div className={"input-group"}>
                                    <div className={"input-group-prepend"}>
                                                    <span className={"input-group-text"}>
                                                        <Icon icon={"key"}/>
                                                    </span>
                                    </div>
                                    <input type={"password"} className={"form-control"}
                                           value={this.state.settings["mail_password"] ?? ""}
                                           placeholder={"(unchanged)"} name={"mail_password"}
                                           id={"mail_password"} onChange={this.onChangeValue.bind(this)}
                                           disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1"}/>
                                </div>
                                <label htmlFor={"mail_from"} className={"mt-2"}>Sender Email Address</label>
                                <div className={"input-group"}>
                                    <div className={"input-group-prepend"}>
                                        <span className={"input-group-text"}>@</span>
                                    </div>
                                    <input type={"email"} className={"form-control"}
                                           value={this.state.settings["mail_from"] ?? ""}
                                           placeholder={"Enter a email address"} name={"mail_from"}
                                           id={"mail_from"} onChange={this.onChangeValue.bind(this)}
                                           disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1"}/>
                                </div>
                                <div className={"row"}>
                                    <div className={"col-6"}>
                                        <label htmlFor={"mail_host"} className={"mt-2"}>SMTP Host</label>
                                        <div className={"input-group"}>
                                            <div className={"input-group-prepend"}>
                                                            <span className={"input-group-text"}>
                                                                <Icon icon={"project-diagram"}/>
                                                            </span>
                                            </div>
                                            <input type={"text"} className={"form-control"}
                                                   value={this.state.settings["mail_host"] ?? ""}
                                                   placeholder={"e.g. smtp.example.com"} name={"mail_host"}
                                                   id={"mail_host"} onChange={this.onChangeValue.bind(this)}
                                                   disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1"}/>
                                        </div>
                                    </div>
                                    <div className={"col-6"}>
                                        <label htmlFor={"mail_port"} className={"mt-2"}>SMTP Port</label>
                                        <div className={"input-group"}>
                                            <div className={"input-group-prepend"}>
                                                            <span className={"input-group-text"}>
                                                                <Icon icon={"project-diagram"}/>
                                                            </span>
                                            </div>
                                            <input type={"number"} className={"form-control"}
                                                   value={parseInt(this.state.settings["mail_port"] ?? "25")}
                                                   placeholder={"smtp port"} name={"mail_port"}
                                                   id={"mail_port"} onChange={this.onChangeValue.bind(this)}
                                                   disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1"}/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button className={"btn btn-secondary ml-2"}
                                        onClick={() => this.onReset("mailErrors", this.mailKeys)}
                                        disabled={this.state.isResetting || this.state.isSaving}>
                                    {this.state.isResetting ?
                                        <span>Resetting&nbsp;<Icon icon={"circle-notch"}/></span> : "Reset"}
                                </button>
                                <button className={"btn btn-success ml-2"}
                                        onClick={() => this.onSave("mailErrors", this.mailKeys)}
                                        disabled={this.state.isResetting || this.state.isSaving}>
                                    {this.state.isSaving ?
                                        <span>Saving&nbsp;<Icon icon={"circle-notch"}/></span> : "Save"}
                                </button>
                            </div>
                            <div className={"mt-3"}>
                                <label htmlFor={"mail_from"} className={"mt-2"}>Send Test E-Mail</label>
                                <div className={"input-group"}>
                                    <div className={"input-group-prepend"}>
                                        <span className={"input-group-text"}>@</span>
                                    </div>
                                    <input type={"email"} className={"form-control"}
                                           value={this.state.test_email}
                                           placeholder={"Enter a email address"}
                                           onChange={(e) => this.setState({
                                               ...this.state,
                                               test_email: e.target.value
                                           })}
                                           disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1"}/>
                                </div>
                                <div className={"form-group form-inline mt-3"}>
                                    <button className={"btn btn-info col-2"}
                                            onClick={() => this.onSendTestMail()}
                                            disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1" || this.state.isSending}>
                                        {this.state.isSending ?
                                            <span>Sending&nbsp;<Icon icon={"circle-notch"}/></span> : "Send Mail"}
                                    </button>
                                    <div className={"col-10"}>
                                        { this.state.unsavedMailSettings ? <span className={"text-red"}>You need to save your mail settings first.</span> : null }
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Collapse>
        </>
    }

    getUncategorisedCard() {

        let keys = [];
        let tr = [];
        for (let key in this.state.settings) {
            if (this.state.settings.hasOwnProperty(key)) {
                if (!this.generalKeys.includes(key) && !this.mailKeys.includes(key)) {
                    keys.push(key);
                    tr.push(<tr key={"tr-" + key}>
                        <td>{key}</td>
                        <td>{this.state.settings[key]}</td>
                    </tr>);
                }
            }
        }

        let errors = [];
        for (let i = 0; i < this.state.etcErrors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError("etcErrors", i)} {...this.state.etcErrors[i]}/>)
        }

        return <>
            <div className={"card-header"} style={{cursor: "pointer"}}
                 onClick={() => this.toggleCollapse("etcOpened")}>
                <h4 className={"card-title"}>
                    <Icon className={"mr-2"} icon={"cogs"}/>
                    General Settings
                </h4>
                <div className={"card-tools"}>
                    <span className={"btn btn-tool btn-sm"}>
                        <Icon icon={this.state.etcOpened ? "angle-up" : "angle-down"}/>
                    </span>
                </div>
            </div>
            <Collapse isOpened={this.state.etcOpened}>
                <div className={"card-body"}>
                    <div className={"row"}>
                        <div className={"col-12 col-lg-6"}>
                            {errors}
                            <table>
                                <thead>
                                    <tr>
                                        <th>Key</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tr}
                                </tbody>
                            </table>
                            <div>
                                <button className={"btn btn-secondary ml-2"} onClick={() => this.onReset("etcErrors", keys)}
                                        disabled={this.state.isResetting || this.state.isSaving}>
                                    {this.state.isResetting ?
                                        <span>Resetting&nbsp;<Icon icon={"circle-notch"}/></span> : "Reset"}
                                </button>
                                <button className={"btn btn-success ml-2"} onClick={() => this.onSave("etcErrors", keys)}
                                        disabled={this.state.isResetting || this.state.isSaving}>
                                    {this.state.isSaving ?
                                        <span>Saving&nbsp;<Icon icon={"circle-notch"}/></span> : "Save"}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Collapse>
        </>
    }

    render() {

        let errors = [];
        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError("errors", i)} {...this.state.errors[i]}/>)
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
                        {this.getGeneralCard()}
                    </div>
                    <div className={"card card-warning"}>
                        {this.getEmailCard()}
                    </div>
                    <div className={"card card-secondary"}>
                        {this.getUncategorisedCard()}
                    </div>
                </div>
            </div>
        </>
    }

    onChangeValue(event) {
        const target = event.target;
        const name = target.name;
        const type = target.type;
        let value = target.value;

        if (type === "checkbox") {
            value = event.target.checked ? "1" : "0";
        }

        let changedMailSettings = false;
        if (name.startsWith("mail_")) {
            changedMailSettings = true;
        }

        this.setState({...this.state, settings: {...this.state.settings, [name]: value},
            unsavedMailSettings: changedMailSettings ? true : this.state.unsavedMailSettings
        });
    }

    onReset(errorKey, keys) {
        this.setState({...this.state, isResetting: true});

        let values = {};
        for (let key of keys) {
            values[key] = this.state.settings[key];
        }

        let mailSettingsSaved = errorKey === "mailErrors";
        this.parent.api.getSettings().then((res) => {
            if (!res.success) {
                let errors = this.state[errorKey].slice();
                errors.push({title: "Error fetching settings", message: res.msg});
                this.setState({...this.state, [errorKey]: errors, isResetting: false});
            } else {
                let newSettings = {...this.state.settings};
                for (let key of keys) {
                    newSettings[key] = res.settings[key] ?? "";
                }
                this.setState({...this.state, settings: newSettings, isResetting: false,
                    unsavedMailSettings: mailSettingsSaved ? false : this.state.unsavedMailSettings});
            }
        });
    }

    onSave(errorKey, keys) {
        this.setState({...this.state, isSaving: true});

        let values = {};
        for (let key of keys) {
            if (key === "mail_password" && !this.state.settings[key]) {
                continue;
            }

            values[key] = this.state.settings[key];
        }

        let mailSettingsSaved = errorKey === "mailErrors";
        this.parent.api.saveSettings(values).then((res) => {
            if (!res.success) {
                let errors = this.state[errorKey].slice();
                errors.push({title: "Error fetching settings", message: res.msg});
                this.setState({...this.state, [errorKey]: errors, isSaving: false});
            } else {
                this.setState({...this.state, isSaving: false, unsavedMailSettings: mailSettingsSaved ? false : this.state.unsavedMailSettings });
            }
        });
    }

    onSendTestMail() {
        this.setState({...this.state, isSending: true});

        this.parent.api.sendTestMail(this.state.test_email).then((res) => {
            let errors = this.state.mailErrors.slice();
            if (!res.success) {
                errors.push({title: "Error sending email", message: res.msg});
                this.setState({...this.state, mailErrors: errors, isSending: false});
            } else {
                errors.push({
                    title: "Success!",
                    message: "E-Mail was successfully sent, check your inbox.",
                    type: "success"
                });
                this.setState({...this.state, mailErrors: errors, isSending: false, test_email: ""});
            }
        });
    }
}
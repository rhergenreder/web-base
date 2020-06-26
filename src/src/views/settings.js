import React from "react";
import {Link} from "react-router-dom";
import Alert from "../elements/alert";
import {Collapse} from "react-collapse/lib/Collapse";
import Icon from "../elements/icon";
import { EditorState, ContentState, convertToRaw } from 'draft-js'
import { Editor } from 'react-draft-wysiwyg'
import draftToHtml from 'draftjs-to-html';
import htmlToDraft from 'html-to-draftjs';
import sanitizeHtml from 'sanitize-html'
import 'react-draft-wysiwyg/dist/react-draft-wysiwyg.css';
import ReactTooltip from "react-tooltip";

export default class Settings extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            errors: [],
            settings: {},
            general: {
                alerts: [],
                isOpen: true,
                isSaving: false,
                isResetting: false,
                keys: ["site_name", "base_url", "user_registration_enabled"]
            },
            mail: {
                alerts: [],
                isOpen: true,
                isSaving: false,
                isResetting: false,
                isSending: false,
                test_email: "",
                unsavedMailSettings: false,
                keys: ["mail_enabled", "mail_host", "mail_port", "mail_username", "mail_password", "mail_from"]
            },
            messages: {
                alerts: [],
                isOpen: true,
                isSaving: false,
                isResetting: false,
                editor: EditorState.createEmpty(),
                isEditing: null,
                keys: ["message_confirm_email", "message_accept_invite", "message_reset_password"]
            },
            recaptcha: {
                alerts: [],
                isOpen: true,
                isSaving: false,
                isResetting: false,
                keys: ["recaptcha_enabled", "recaptcha_public_key", "recaptcha_private_key"]
            },
            uncategorised: {
                alerts: [],
                isOpen: true,
                isSaving: false,
                isResetting: false,
                settings: []
            },
        };

        this.parent = {
            api: props.api,
            showDialog: props.showDialog
        };

        this.hiddenKeys = [
            "recaptcha_private_key",
            "mail_password",
            "jwt_secret"
        ];
    }

    isDefaultKey(key) {
        key = key.trim();
        return this.state.general.keys.includes(key)
            || this.state.mail.keys.includes(key)
            || this.state.messages.keys.includes(key)
            || this.hiddenKeys.includes(key);
    }

    getUncategorisedValues(res) {
        let uncategorised = [];
        for(let key in res.settings) {
            if (res.settings.hasOwnProperty(key) && !this.isDefaultKey(key)) {
                uncategorised.push({key: key, value: res.settings[key]});
            }
        }

        return uncategorised;
    }

    onDeleteUncategorisedProp(index) {
        if (index < 0 || index >= this.state.uncategorised.settings.length) {
            return;
        }

        let props = this.state.uncategorised.settings.slice();
        props.splice(index, 1);
        this.setState({ ...this.state, uncategorised: { ...this.state.uncategorised, settings: props }});
    }

    onChangeUncategorisedValue(event, index, isKey) {
        if (index < 0 || index >= this.state.uncategorised.settings.length) {
            return;
        }

        let props = this.state.uncategorised.settings.slice();
        if (isKey) {
            props[index].key = event.target.value;
        } else {
            props[index].value = event.target.value;
        }
        this.setState({ ...this.state, uncategorised: { ...this.state.uncategorised, settings: props }});
    }

    onAddUncategorisedProperty() {
        let props = this.state.uncategorised.settings.slice();
        props.push({key: "", value: ""});
        this.setState({ ...this.state, uncategorised: { ...this.state.uncategorised, settings: props }});
    }

    componentDidMount() {
        this.parent.api.getSettings().then((res) => {
            if (res.success) {
                let newState = {
                    ...this.state,
                    settings: res.settings,
                    uncategorised: { ...this.state.uncategorised, settings: this.getUncategorisedValues(res) }
                };

                this.setState(newState);
            } else {
                let errors = this.state.errors.slice();
                errors.push({title: "Error fetching settings", message: res.msg});
                this.setState({...this.state, errors: errors });
            }
        });
    }

    removeError(i, category = null) {
        if (category) {
            if (i >= 0 && i < this.state[category].alerts.length) {
                let alerts = this.state[category].alerts.slice();
                alerts.splice(i, 1);
                this.setState({...this.state, [category]: {...this.state[category], alerts: alerts}});
            }
        } else {
            if (i >= 0 && i < this.state.errors.length) {
                let errors = this.state.errors.slice();
                errors.splice(i, 1);
                this.setState({...this.state, errors: errors});
            }
        }
    }

    toggleCollapse(category) {
        this.setState({...this.state, [category]: {...this.state[category], isOpen: !this.state[category].isOpen}});
    }

    createCard(category, color, icon, title, content) {

        let alerts = [];
        for (let i = 0; i < this.state[category].alerts.length; i++) {
            alerts.push(<Alert key={"alert-" + i}
                               onClose={() => this.removeError(i, category)} {...this.state[category].alerts[i]}/>)
        }

        return <div className={"card card-" + color} key={"card-" + category}>
            <div className={"card-header"} style={{cursor: "pointer"}}
                 onClick={() => this.toggleCollapse(category)}>
                <h4 className={"card-title"}>
                    <Icon className={"mr-2"} icon={icon} type={icon==="google"?"fab":"fas"} />
                    {title}
                </h4>
                <div className={"card-tools"}>
                    <span className={"btn btn-tool btn-sm"}>
                        <Icon icon={this.state[category].isOpen ? "angle-up" : "angle-down"}/>
                    </span>
                </div>
            </div>
            <Collapse isOpened={this.state[category].isOpen}>
                <div className={"card-body"}>
                    <div className={"row"}>
                        <div className={"col-12 col-lg-6"}>
                            {alerts}
                            {content}
                            <div>
                                <button className={"btn btn-secondary"} onClick={() => this.onReset(category)}
                                        disabled={this.state[category].isResetting || this.state[category].isSaving}>
                                    {this.state[category].isResetting ?
                                        <span>Resetting&nbsp;<Icon icon={"circle-notch"}/></span> : "Reset"}
                                </button>
                                <button className={"btn btn-success ml-2"} onClick={() => this.onSave(category)}
                                        disabled={this.state[category].isResetting || this.state[category].isSaving}>
                                    {this.state[category].isSaving ?
                                        <span>Saving&nbsp;<Icon icon={"circle-notch"}/></span> : "Save"}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Collapse>
        </div>
    }

    createGeneralForm() {
        return <>
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
                           checked={(this.state.settings["user_registration_enabled"] ?? "0") === "1"}
                           onChange={this.onChangeValue.bind(this)}/>
                    <label className={"form-check-label"}
                           htmlFor={"user_registration_enabled"}>
                        Allow anyone to register an account
                    </label>
                </div>
            </div>
        </>
    }

    createMailForm() {
        return <>
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
            <div className={"mt-3"}>
                <label htmlFor={"mail_from"} className={"mt-2"}>Send Test E-Mail</label>
                <div className={"input-group"}>
                    <div className={"input-group-prepend"}>
                        <span className={"input-group-text"}>@</span>
                    </div>
                    <input type={"email"} className={"form-control"}
                           value={this.state.mail.test_email}
                           placeholder={"Enter a email address"}
                           onChange={(e) => this.setState({
                               ...this.state,
                               mail: {...this.state.mail, test_email: e.target.value},
                           })}
                           disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1"}/>
                </div>
                <div className={"form-group form-inline mt-3"}>
                    <button className={"btn btn-info col-2"}
                            onClick={() => this.onSendTestMail()}
                            disabled={(this.state.settings["mail_enabled"] ?? "0") !== "1" || this.state.mail.isSending}>
                        {this.state.mail.isSending ?
                            <span>Sending&nbsp;<Icon icon={"circle-notch"}/></span> : "Send Mail"}
                    </button>
                    <div className={"col-10"}>
                        {this.state.mail.unsavedMailSettings ?
                            <span className={"text-red"}>You need to save your mail settings first.</span> : null}
                    </div>
                </div>
            </div>
        </>
    }

    getMessagesForm() {

        const editor = <Editor
            editorState={this.state.messages.editor}
            onEditorStateChange={this.onEditorStateChange.bind(this)}
        />;

        let messageTemplates = {
            "message_confirm_email": "Confirm E-Mail Message",
            "message_accept_invite": "Accept Invitation Message",
            "message_reset_password": "Reset Password Message",
        };

        let formGroups = [];
        for (let key in messageTemplates) {
            let title = messageTemplates[key];
            if (this.state.messages.isEditing === key) {
                formGroups.push(
                    <div className={"form-group"} key={"group-" + key}>
                        <label htmlFor={key}>
                            { title }
                            <ReactTooltip id={"tooltip-" + key} />
                            <Icon icon={"times"} className={"ml-2 text-danger"} style={{cursor: "pointer"}}
                                  onClick={() => this.closeEditor(false)} data-type={"error"}
                                  data-tip={"Discard Changes"} data-place={"top"} data-effect={"solid"}
                                  data-for={"tooltip-" + key}
                            />
                            <Icon icon={"check"} className={"ml-2 text-success"} style={{cursor: "pointer"}}
                                  onClick={() => this.closeEditor(true)} data-type={"success"}
                                  data-tip={"Save Changes"} data-place={"top"} data-effect={"solid"}
                                  data-for={"tooltip-" + key}
                            />
                        </label>
                        { editor }
                    </div>
                );
            } else {
                formGroups.push(
                    <div className={"form-group"} key={"group-" + key}>
                        <ReactTooltip id={"tooltip-" + key} />
                        <label htmlFor={key}>
                            { title }
                            <Icon icon={"pencil-alt"} className={"ml-2"} style={{cursor: "pointer"}}
                                  onClick={() => this.openEditor(key)} data-type={"info"}
                                  data-tip={"Edit Template"} data-place={"top"} data-effect={"solid"}
                                  data-for={"tooltip-" + key}
                            />
                        </label>
                        <div className={"p-2 text-black"} style={{backgroundColor: "#d2d6de"}} dangerouslySetInnerHTML={{ __html: sanitizeHtml(this.state.settings[key] ?? "") }} />
                    </div>
                );
            }
        }

        return formGroups;
    }

    getRecaptchaForm() {
        return <>
            <div className={"form-group mt-2"}>
                <div className={"form-check"}>
                    <input type={"checkbox"} className={"form-check-input"}
                           name={"recaptcha_enabled"} id={"recaptcha_enabled"}
                           checked={(this.state.settings["recaptcha_enabled"] ?? "0") === "1"}
                           onChange={this.onChangeValue.bind(this)}/>
                    <label className={"form-check-label"} htmlFor={"recaptcha_enabled"}>
                        Enable Google's reCaptcha
                    </label>
                </div>
            </div>
            <hr className={"m-2"}/>
            <label htmlFor={"recaptcha_public_key"} className={"mt-2"}>reCaptcha Site Key</label>
            <div className={"input-group"}>
                <div className={"input-group-prepend"}>
                    <span className={"input-group-text"}>
                        <Icon icon={"unlock"}/>
                    </span>
                </div>
                <input type={"text"} className={"form-control"}
                       value={this.state.settings["recaptcha_public_key"] ?? ""}
                       placeholder={"Enter site key"} name={"recaptcha_public_key"}
                       id={"recaptcha_public_key"} onChange={this.onChangeValue.bind(this)}
                       disabled={(this.state.settings["recaptcha_enabled"] ?? "0") !== "1"}/>
            </div>
            <label htmlFor={"recaptcha_private_key"} className={"mt-2"}>reCaptcha Secret Key</label>
            <div className={"input-group mb-3"}>
                <div className={"input-group-prepend"}>
                    <span className={"input-group-text"}>
                        <Icon icon={"lock"}/>
                    </span>
                </div>
                <input type={"password"} className={"form-control"}
                       value={this.state.settings["recaptcha_private_key"] ?? ""}
                       placeholder={"(unchanged)"} name={"recaptcha_private_key"}
                       id={"mail_password"} onChange={this.onChangeValue.bind(this)}
                       disabled={(this.state.settings["recaptcha_enabled"] ?? "0") !== "1"}/>
            </div>
          </>
    }

    getUncategorizedForm() {
        let tr = [];

        for(let i = 0; i < this.state.uncategorised.settings.length; i++) {
            let key = this.state.uncategorised.settings[i].key;
            let value = this.state.uncategorised.settings[i].value;
            tr.push(
                <tr key={"uncategorised-" + i} className={(i % 2 === 0) ? "even" : "odd"}>
                    <td>
                        <input className={"form-control"} type={"text"} value={key} maxLength={32} placeholder={"Key"}
                               onChange={(e) => this.onChangeUncategorisedValue(e, i, true)} />
                    </td>
                    <td>
                        <input className={"form-control"} type={"text"} value={value} placeholder={"value"}
                               onChange={(e) => this.onChangeUncategorisedValue(e, i, false)} />
                    </td>
                    <td className={"text-center align-middle"}>
                        <ReactTooltip id={"tooltip-uncategorised-" + i} />
                        <Icon icon={"trash"} className={"text-danger"} style={{cursor: "pointer"}}
                            onClick={() => this.onDeleteUncategorisedProp(i)}  data-type={"error"}
                              data-tip={"Delete property"} data-place={"right"} data-effect={"solid"}
                              data-for={"tooltip-uncategorised-" + i}
                        />
                    </td>
                </tr>
            );
        }

        return <>
            <table className={"table table-bordered table-hover dataTable dtr-inline"}>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th className={"text-center"}><Icon icon={"tools"}/></th>
                    </tr>
                </thead>
                <tbody>
                    {tr}
                </tbody>
            </table>
            <div className={"mt-2 mb-3"}>
                <button className={"btn btn-info"} onClick={() => this.onAddUncategorisedProperty()} >
                    <Icon icon={"plus"} className={"mr-2"} /> Add property
                </button>
            </div>
        </>
    }

    render() {

        let errors = [];
        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i}
                               onClose={() => this.removeError("errors", i)} {...this.state.errors[i]}/>)
        }

        const categories = {
            "general": {color: "primary", icon: "cogs", title: "General Settings", content: this.createGeneralForm()},
            "mail": {color: "warning", icon: "envelope", title: "Mail Settings", content: this.createMailForm()},
            "messages": {color: "info", icon: "copy", title: "Message Templates", content: this.getMessagesForm()},
            "recaptcha": {color: "danger", icon: "google", title: "Google reCaptcha", content: this.getRecaptchaForm()},
            "uncategorised": {color: "secondary", icon: "stream", title: "Uncategorised", content: this.getUncategorizedForm()},
        };

        let cards = [];
        for (let name in categories) {
            let category = categories[name];
            cards.push(this.createCard(name, category.color, category.icon, category.title, category.content));
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
                    {cards}
                </div>
            </div>
            <ReactTooltip />
        </>
    }

    onEditorStateChange(editorState) {
        this.setState({
            ...this.state,
            messages: {
                ...this.state.messages,
                editor: editorState
            }
        });
    };

    onChangeValue(event) {
        const target = event.target;
        const name = target.name;
        const type = target.type;
        let value = target.value;

        if (type === "checkbox") {
            value = event.target.checked ? "1" : "0";
        }

        let changedMailSettings = false;
        if (this.state.mail.keys.includes(name)) {
            changedMailSettings = true;
        }

        let newState = {...this.state, settings: {...this.state.settings, [name]: value}};
        if (changedMailSettings) {
            newState.mail = {...this.state.mail, unsavedMailSettings: true};
        }

        this.setState(newState);
    }

    onReset(category) {
        this.setState({...this.state, [category]: {...this.state[category], isResetting: true}});

        this.parent.api.getSettings().then((res) => {
            if (!res.success) {
                let alerts = this.state[category].alerts.slice();
                alerts.push({title: "Error fetching settings", message: res.msg});
                this.setState({
                    ...this.state,
                    [category]: {...this.state[category], alerts: alerts, isResetting: false}
                });
            } else {
                let newState = { ...this.state };
                let categoryUpdated = {...this.state[category], isResetting: false};
                let newSettings = {...this.state.settings};

                if (category === "uncategorised") {
                    categoryUpdated.settings = this.getUncategorisedValues(res);
                    for (let key in res.settings) {
                        if (res.settings.hasOwnProperty(key) && !this.isDefaultKey(key)) {
                            newSettings[key] = res.settings[key] ?? "";
                        }
                    }
                } else {
                    for (let key of this.state[category].keys) {
                        newSettings[key] = res.settings[key] ?? "";
                    }

                    if (category === "mail") {
                        categoryUpdated.unsavedMailSettings = false;
                    } else if (category === "messages") {
                        categoryUpdated.isEditing = null;
                    }
                }

                newState.settings = newSettings;
                newState[category] = categoryUpdated;
                this.setState(newState);
            }
        });
    }

    onSave(category) {
        this.setState({...this.state, [category]: {...this.state[category], isSaving: true}});

        if (category === "messages" && this.state.messages.isEditing) {
            this.closeEditor(true, () => this.onSave(category));
        }

        let values = {};
        if (category === "uncategorised") {
            for (let prop of this.state.uncategorised.settings) {
                if (prop.key) {
                    values[prop.key] = prop.value;
                    if (this.isDefaultKey(prop.key)) {
                        this.parent.showDialog("You cannot use this key as property key: " + prop.key, "System specific key");
                        this.setState({...this.state, [category]: {...this.state[category], isSaving: false}});
                        return;
                    }
                }
            }

            for (let key in this.state.settings) {
                if (this.state.settings.hasOwnProperty(key) && !this.isDefaultKey(key) && !values.hasOwnProperty(key)) {
                    values[key] = null;
                }
            }
        } else {
            for (let key of this.state[category].keys) {
                if (this.hiddenKeys.includes(key) && !this.state.settings[key]) {
                    continue;
                }

                values[key] = this.state.settings[key];
            }
        }

        this.parent.api.saveSettings(values).then((res) => {
            let alerts = this.state[category].alerts.slice();
            let categoryUpdated = {...this.state[category], isSaving: false};

            if (!res.success) {
                alerts.push({title: "Error fetching settings", message: res.msg});
            } else {
                alerts.push({title: "Success", message: "Settings were successfully saved.", type: "success"});
                if (category === "mail") categoryUpdated.unsavedMailSettings = false;
                this.setState({...this.state, [category]: categoryUpdated});
            }

            categoryUpdated.alerts = alerts;
            this.setState({...this.state, [category]: categoryUpdated});
        });
    }

    onSendTestMail() {
        this.setState({...this.state, mail: {...this.state.mail, isSending: true}});

        console.log(this.state.mail);
        this.parent.api.sendTestMail(this.state.mail.test_email).then((res) => {
            let alerts = this.state.mail.alerts.slice();
            let newState = {...this.state.mail, isSending: false};
            if (!res.success) {
                alerts.push({title: "Error sending email", message: res.msg});
            } else {
                alerts.push({
                    title: "Success!",
                    message: "E-Mail was successfully sent, check your inbox.",
                    type: "success"
                });
                newState.test_email = "";
            }

            newState.alerts = alerts;
            this.setState({...this.state, mail: newState});
        });
    }

    closeEditor(save, callback = null) {
        if (this.state.messages.isEditing) {
            const key = this.state.messages.isEditing;
            let newState = { ...this.state, messages: {...this.state.messages, isEditing: null }};

            if (save) {
                newState.settings = {
                    ...this.state.settings,
                    [key]: draftToHtml(convertToRaw(this.state.messages.editor.getCurrentContent())),
                };
            }

            callback = callback || function () { };
            this.setState(newState, callback);
        }
    }

    openEditor(message) {
        this.closeEditor(true);
        const contentBlock = htmlToDraft(this.state.settings[message] ?? "");
        if (contentBlock) {
            const contentState = ContentState.createFromBlockArray(contentBlock.contentBlocks);
            const editorState = EditorState.createWithContent(contentState);
            this.setState({
                ...this.state,
                messages: {
                    ...this.state.messages,
                    isEditing: message,
                    editor: editorState
                }
            });
        }
    }
}
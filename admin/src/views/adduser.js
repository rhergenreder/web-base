import * as React from "react";
import {Link} from "react-router-dom";
import Alert from "../elements/alert";
import Icon from "../elements/icon";
import ReactTooltip from 'react-tooltip'

export default class CreateUser extends React.Component {

    constructor(props) {
        super(props);

        this.parent = {
            showDialog: props.showDialog || function () { },
            api: props.api,
        };

        this.state = {
            errors: [],
            sendInvite: true,
            username: "",
            email: "",
            password: "",
            confirmPassword: ""
        }
    }

    removeError(i) {
        if (i >= 0 && i < this.state.errors.length) {
            let errors = this.state.errors.slice();
            errors.splice(i, 1);
            this.setState({...this.state, errors: errors});
        }
    }

    render() {

        let errors = [];
        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.errors[i]}/>)
        }

        let passwordForm = null;
        if (!this.state.sendInvite) {
            passwordForm = <div className={"mt-2"}>
                <div className={"form-group"}>
                    <label htmlFor={"password"}>Password</label>
                    <input type={"password"} className={"form-control"} placeholder={"Password"}
                           id={"password"} name={"password"} value={this.state.password}
                           onChange={this.onChangeInput.bind(this)}/>
                </div>
                <div className={"form-group"}>
                    <label htmlFor={"confirmPassword"}>Confirm Password</label>
                    <input type={"password"} className={"form-control"} placeholder={"Confirm Password"}
                           id={"confirmPassword"} name={"confirmPassword"} value={this.state.confirmPassword}
                           onChange={this.onChangeInput.bind(this)}/>
                </div>
            </div>
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
                        {errors}
                        <form role={"form"} onSubmit={(e) => this.submitForm(e)}>
                            <div className={"form-group"}>
                                <label htmlFor={"username"}>Username</label>
                                <input type={"text"} className={"form-control"} placeholder={"Enter username"}
                                       name={"username"} id={"username"} maxLength={32} value={this.state.username}
                                       onChange={this.onChangeInput.bind(this)}/>
                            </div>
                            <div className={"form-group"}>
                                <label htmlFor={"email"}>E-Mail</label>
                                <input type={"email"} className={"form-control"} placeholder={"E-Mail address"}
                                       id={"email"} name={"email"} maxLength={64} value={this.state.email}
                                       onChange={this.onChangeInput.bind(this)}/>
                            </div>
                            <div className={"form-check"}>
                                <input type={"checkbox"} className={"form-check-input"}
                                       onChange={() => this.onCheckboxChange()}
                                       id={"sendInvite"} name={"sendInvite"} defaultChecked={this.state.sendInvite}/>
                                <label className={"form-check-label"} htmlFor={"sendInvite"}>
                                    Send Invitation
                                    <Icon icon={"question-circle"} className={"ml-2"} style={{"color": "#0069d9"}}
                                          data-tip={"The user will receive an invitation token via email and can choose the password on his own."}
                                          data-type={"info"} data-place={"right"} data-effect={"solid"}/>
                                </label>
                            </div>
                            {passwordForm}
                            <Link to={"/admin/users"} className={"btn btn-info mt-2 mr-2"}>
                                <Icon icon={"arrow-left"}/>
                                &nbsp;Back
                            </Link>
                            <button type={"submit"} className={"btn btn-primary mt-2"}>Submit</button>
                        </form>
                    </div>
                </div>
            </div>
            <ReactTooltip/>
        </>;
    }

    submitForm(e) {
        e.preventDefault();

        const requiredFields = (this.state.sendInvite ?
            ["username", "email"] :
            ["username", "password", "confirmPassword"]);

        let missingFields = [];
        for (const field of requiredFields) {
            if (!this.state[field]) {
                missingFields.push(field);
            }
        }

        if (missingFields.length > 0) {
            let errors = this.state.errors.slice();
            errors.push({title: "Missing input", message: "The following fields are missing: " + missingFields.join(", "), type: "warning"});
            this.setState({ ...this.state, errors: errors });
            return;
        }

        const username = this.state.username;
        const email = this.state.email || "";
        const password = this.state.password;
        const confirmPassword = this.state.confirmPassword;

        if (this.state.sendInvite) {
            this.parent.api.inviteUser(username, email).then((res) => {
                let errors = this.state.errors.slice();
                if (!res.success) {
                    errors.push({ title: "Error inviting User", message: res.msg, type: "error" });
                    this.setState({ ...this.state, errors: errors });
                } else {
                    errors.push({ title: "Success", message: "The invitation was successfully sent.", type: "success" });
                    this.setState({ ...this.state, errors: errors, username: "", email: "" });
                }
            });
        } else {
            this.parent.api.createUser(username, email, password, confirmPassword).then((res) => {
                let errors = this.state.errors.slice();
                if (!res.success) {
                    errors.push({ title: "Error creating User", message: res.msg, type: "error" });
                    this.setState({ ...this.state, errors: errors, password: "", confirmPassword: "" });
                } else {
                    errors.push({ title: "Success", message: "The user was successfully created.", type: "success" });
                    this.setState({ ...this.state, errors: errors, username: "", email: "", password: "", confirmPassword: "" });
                }
            });
        }
    }

    onCheckboxChange() {
        this.setState({
            ...this.state,
            sendInvite: !this.state.sendInvite,
        });
    }

    onChangeInput(event) {
        const target = event.target;
        const value = target.value;
        const name = target.name;
        this.setState({ ...this.state, [name]: value });
    }
}
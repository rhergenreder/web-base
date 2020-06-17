import * as React from "react";
import {Link} from "react-router-dom";
import Alert from "../elements/alert";
import Icon from "../elements/icon";
import ReactTooltip from 'react-tooltip'

export default class CreateUser extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            errors: [],
            sendInvite: true
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
                           id={"password"} name={"password"}/>
                </div>
                <div className={"form-group"}>
                    <label htmlFor={"confirmPassword"}>Confirm Password</label>
                    <input type={"password"} className={"form-control"} placeholder={"Confirm Password"}
                           id={"confirmPassword"} name={"confirmPassword"}/>
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
                {errors}
                <div className={"row"}>
                    <div className={"col-lg-6 p-3"}>
                        <form role={"form"} onSubmit={(e) => this.submitForm(e)}>
                            <div className={"form-group"}>
                                <label htmlFor={"username"}>Username</label>
                                <input type={"text"} className={"form-control"} placeholder={"Enter username"}
                                       name={"username"} id={"username"} maxLength={32}/>
                            </div>
                            <div className={"form-group"}>
                                <label htmlFor={"email"}>E-Mail</label>
                                <input type={"email"} className={"form-control"} placeholder={"E-Mail address"}
                                       id={"email"} name={"email"} maxLength={64}/>
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
    }

    onCheckboxChange() {
        this.setState({
            ...this.state,
            sendInvite: !this.state.sendInvite,
        });
    }
}
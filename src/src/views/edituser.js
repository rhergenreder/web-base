import * as React from "react";
import Icon from "../elements/icon";
import Alert from "../elements/alert";
import {Link} from "react-router-dom";

export default class EditUser extends React.Component {

    constructor(props) {
        super(props);
        this.parent = {
            api: props.api
        };

        this.state = {
            user: {},
            errors: [],
            fetchError: null,
            loaded: false,
            isSaving: false
        }
    }

    removeError(i) {
        if (i >= 0 && i < this.state.errors.length) {
            let errors = this.state.errors.slice();
            errors.splice(i, 1);
            this.setState({...this.state, errors: errors});
        }
    }

    componentDidMount() {
        this.parent.api.getUser(this.props.match.params["userId"]).then((res) => {
            if (res.success) {
                this.setState({ ...this.state, user: res.user, loaded: true });
            } else {
                this.setState({ ...this.state, fetchError: res.msg, loaded: true });
            }
        });
    }

    render() {

        if (!this.state.loaded) {
            return <h2 className={"text-center"}>
                Loading…<br/>
                <Icon icon={"spinner"} className={"mt-3 text-muted fa-2x"}/>
            </h2>
        }

        let errors = [];
        let form = null;
        if(this.state.fetchError) {
            errors.push(
                <Alert key={"error-fetch"} title={"Error fetching user details"} type={"danger"} message={
                    <div>{this.state.fetchError}<br/>You can meanwhile return to the&nbsp;
                        <Link to={"/admin/users"}>user overview</Link>
                    </div>
                }/>
            )
        } else {
            for (let i = 0; i < this.state.errors.length; i++) {
                errors.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.errors[i]}/>)
            }

            form = <form role={"form"} onSubmit={(e) => e.preventDefault()}>
                <div className={"form-group"}>
                    <label htmlFor={"username"}>Username</label>
                    <input type={"text"} className={"form-control"} placeholder={"Enter username"}
                           name={"username"} id={"username"} maxLength={32} value={this.state.user.name}/>
                </div>
                <div className={"form-group"}>
                    <label htmlFor={"email"}>E-Mail</label>
                    <input type={"email"} className={"form-control"} placeholder={"E-Mail address"}
                           id={"email"} name={"email"} maxLength={64} value={this.state.user.email} />
                </div>

                <Link to={"/admin/users"} className={"btn btn-info mt-2 mr-2"}>
                    <Icon icon={"arrow-left"}/>
                    &nbsp;Back
                </Link>
                { this.state.isSaving
                    ? <button type={"submit"} className={"btn btn-primary mt-2"} disabled>Saving…&nbsp;<Icon icon={"circle-notch"} /></button>
                    : <button type={"submit"} className={"btn btn-primary mt-2"}>Save</button>
                }
            </form>
        }

        return <>
            <div className="content-header">
                <div className="container-fluid">
                    <div className="row mb-2">
                        <div className="col-sm-6">
                            <h1 className="m-0 text-dark">Edit User</h1>
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
                        {form}
                    </div>
                </div>
            </div>
        </>
    }
}
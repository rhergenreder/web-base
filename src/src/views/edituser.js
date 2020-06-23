import * as React from "react";
import Icon from "../elements/icon";
import Alert from "../elements/alert";
import {Link} from "react-router-dom";
import "../include/select2.min.css";

export default class EditUser extends React.Component {

    constructor(props) {
        super(props);
        this.parent = {
            api: props.api
        };

        this.state = {
            user: {},
            alerts: [],
            fetchError: null,
            loaded: false,
            isSaving: false,
            groups: { },
            searchString: "",
            searchActive: false
        };

        this.searchBox = React.createRef();
    }

    removeAlert(i) {
        if (i >= 0 && i < this.state.alerts.length) {
            let alerts = this.state.alerts.slice();
            alerts.splice(i, 1);
            this.setState({...this.state, alerts: alerts});
        }
    }

    componentDidMount() {
        this.parent.api.getUser(this.props.match.params["userId"]).then((res) => {
            if (res.success) {
                this.setState({ ...this.state, user: {... res.user, password: ""} });
                this.parent.api.fetchGroups(1, 50).then((res) => {
                    if (res.success) {
                        this.setState({ ...this.state, groups: res.groups, loaded: true });
                    } else {
                        this.setState({ ...this.state, fetchError: res.msg, loaded: true });
                    }
                });
            } else {
                this.setState({ ...this.state, fetchError: res.msg, loaded: true });
            }
        });
    }

    onChangeInput(event) {
        const target = event.target;
        const value = target.value;
        const name = target.name;

        if (name === "search") {
            this.setState({ ...this.state, searchString: value });
        } else {
            this.setState({ ...this.state, user: { ...this.state.user, [name]: value } });
        }
    }

    onToggleSearch(e) {
        e.stopPropagation();
        this.setState({ ...this.state, searchActive: !this.state.searchActive });
        this.searchBox.current.focus();
    }

    onSubmitForm(event) {
        event.preventDefault();
        event.stopPropagation();
        const id = this.props.match.params["userId"];
        const username = this.state.user["name"];
        const email = this.state.user["email"];
        let password = this.state.user["password"].length > 0 ? this.state.user["password"] : null;
        let groups = Object.keys(this.state.user.groups);

        this.setState({ ...this.state, isSaving: true});
        this.parent.api.editUser(id, username, email, password, groups).then((res) => {
            let alerts = this.state.alerts.slice();

            if (res.success) {
                alerts.push({ title: "Success", message: "User was successfully updated.", type: "success" });
                this.setState({ ...this.state, isSaving: false, alerts: alerts, user: { ...this.state.user, password: "" } });
            } else {
                alerts.push({ title: "Error updating user", message: res.msg, type: "danger" });
                this.setState({ ...this.state, isSaving: false, alerts: alerts, user: { ...this.state.user, password: "" } });
            }
        });
    }

    onRemoveGroup(event, groupId) {
        event.stopPropagation();
        if (this.state.user.groups.hasOwnProperty(groupId)) {
            let groups = { ...this.state.user.groups };
            delete groups[groupId];
            this.setState({ ...this.state, user: { ...this.state.user, groups: groups }});
        }
    }

    onAddGroup(event, groupId) {
        event.stopPropagation();
        if (!this.state.user.groups.hasOwnProperty(groupId)) {
            let groups = { ...this.state.user.groups, [groupId]: { ...this.state.groups[groupId] } };
            this.setState({ ...this.state, user: { ...this.state.user, groups: groups }, searchActive: false, searchString: "" });
        }
    }

    render() {
        if (!this.state.loaded) {
            return <h2 className={"text-center"}>
                Loading…<br/>
                <Icon icon={"spinner"} className={"mt-3 text-muted fa-2x"}/>
            </h2>
        }

        let alerts = [];
        let form = null;
        if(this.state.fetchError) {
            alerts.push(
                <Alert key={"error-fetch"} title={"Error fetching data"} type={"danger"} message={
                    <div>{this.state.fetchError}<br/>You can meanwhile return to the&nbsp;
                        <Link to={"/admin/users"}>user overview</Link>
                    </div>
                }/>
            )
        } else {

            for (let i = 0; i < this.state.alerts.length; i++) {
                alerts.push(<Alert key={"error-" + i} onClose={() => this.removeAlert(i)} {...this.state.alerts[i]}/>)
            }

            let possibleOptions = [];
            let renderedOptions = [];
            for (let groupId in this.state.groups) {
                if (this.state.groups.hasOwnProperty(groupId)) {
                    let groupName = this.state.groups[groupId].name;
                    let groupColor = this.state.groups[groupId].color;
                    if (this.state.user.groups.hasOwnProperty(groupId)) {
                        renderedOptions.push(
                            <li className={"select2-selection__choice"} key={"group-" + groupId} title={groupName} style={{backgroundColor: groupColor}}>
                                <span className="select2-selection__choice__remove" role="presentation"
                                      onClick={(e) => this.onRemoveGroup(e, groupId)}>
                                    ×
                                </span>
                                {groupName}
                            </li>
                        );
                    } else {
                        if (this.state.searchString.length === 0 || groupName.toLowerCase().includes(this.state.searchString.toLowerCase())) {
                            possibleOptions.push(
                                <li className={"select2-results__option"} role={"option"} key={"group-" + groupId} aria-selected={false}
                                    onClick={(e) => this.onAddGroup(e, groupId)}>
                                    {groupName}
                                </li>
                            );
                        }
                    }
                }
            }

            let searchWidth = "100%";
            let placeholder = "Select Groups";
            let searchVisible = (this.state.searchString.length > 0 || this.state.searchActive) ? "block" : "none";
            if (renderedOptions.length > 0) {
                searchWidth = (0.75 + this.state.searchString.length * 0.75) + "em";
                placeholder = "";
            }

            if (this.state.searchString.length > 0 && possibleOptions.length === 0) {
                possibleOptions.push(
                    <li className={"select2-results__option"} role={"option"} key={"group-notfound"} aria-selected={true}>
                        Group not found
                    </li>
                );
            }

            form = <form role={"form"} onSubmit={this.onSubmitForm.bind(this)}>
                <div className={"form-group"}>
                    <label htmlFor={"username"}>Username</label>
                    <input type={"text"} className={"form-control"} placeholder={"Enter username"}
                           name={"username"} id={"username"} maxLength={32} value={this.state.user.name}
                           onChange={this.onChangeInput.bind(this)}/>
                </div>
                <div className={"form-group"}>
                    <label htmlFor={"email"}>E-Mail</label>
                    <input type={"email"} className={"form-control"} placeholder={"E-Mail address"}
                           id={"email"} name={"email"} maxLength={64} value={this.state.user.email}
                           onChange={this.onChangeInput.bind(this)}/>
                </div>

                <div className={"form-group"}>
                    <label htmlFor={"password"}>Password</label>
                    <input type={"password"} className={"form-control"} placeholder={"(unchanged)"}
                           id={"password"} name={"password"} value={this.state.user.password}
                           onChange={this.onChangeInput.bind(this)}/>
                </div>

                <div className={"form-group position-relative"}>
                    <label>Groups</label>
                    <span className={"select2 select2-container select2-container--default select2-container--below"}
                          dir={"ltr"} style={{width: "100%"}} >
                        <span className="selection">
                            <span className={"select2-selection select2-selection--multiple"} role={"combobox"} aria-haspopup={"true"}
                                  aria-expanded={false} aria-disabled={false} onClick={this.onToggleSearch.bind(this)}>
                                <ul className={"select2-selection__rendered"}>
                                    {renderedOptions}
                                    <li className={"select2-search select2-search--inline"}>
                                        <input className={"select2-search__field"} type={"search"} tabIndex={0}
                                           autoComplete={"off"} autoCorrect={"off"} autoCapitalize={"none"} spellCheck={false}
                                           role={"searchbox"} aria-autocomplete={"list"} placeholder={placeholder}
                                           name={"search"} style={{width: searchWidth}} value={this.state.searchString}
                                           onChange={this.onChangeInput.bind(this)} ref={this.searchBox} />
                                    </li>
                                </ul>
                                </span>
                            </span>
                        <span className="dropdown-wrapper" aria-hidden="true"/>
                    </span>
                    <span className={"select2-container select2-container--default select2-container--open"}
                          style={{position: "absolute", bottom: 0, left: 0, width: "100%", display: searchVisible}}>
                        <span className={"select2-dropdown select2-dropdown--below"} dir={"ltr"}>
                            <span className={"select2-results"}>
                                <ul className={"select2-results__options"} role={"listbox"}
                                    aria-multiselectable={true} aria-expanded={true} aria-hidden={false}>
                                    {possibleOptions}
                                </ul>
                            </span>
                        </span>
                    </span>
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
                        {alerts}
                        {form}
                    </div>
                </div>
            </div>
        </>
    }
}
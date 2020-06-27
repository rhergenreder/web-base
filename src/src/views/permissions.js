import * as React from "react";
import {Link} from "react-router-dom";
import Icon from "../elements/icon";
import Alert from "../elements/alert";
import ReactTooltip from "react-tooltip";

export default class PermissionSettings extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            alerts: [],
            permissions: [],
            groups: {},
            isSaving: false,
            isResetting: false
        };

        this.parent = {
            api: props.api
        }
    }

    componentDidMount() {
        this.fetchPermissions()
    }

    fetchPermissions() {
        this.parent.api.fetchPermissions().then((res) => {
            if (!res.success) {
                let alerts = this.state.alerts.slice();
                alerts.push({ message: res.msg, title: "Error fetching permissions" });
                this.setState({...this.state, alerts: alerts, isResetting: false});
            } else {
                this.setState({...this.state, groups: res.groups, permissions: res.permissions, isResetting: false});
            }
        });
    }

    removeAlert(i) {
        if (i >= 0 && i < this.state.alerts.length) {
            let alerts = this.state.alerts.slice();
            alerts.splice(i, 1);
            this.setState({...this.state, alerts: alerts});
        }
    }

    onChangeMethod(e, index) {
        if (index < 0 || index >= this.state.permissions.length) {
            return;
        }

        let value = e.target.value;
        let newPermissions = this.state.permissions.slice();
        newPermissions[index].method = value;
        this.setState({ ...this.state, permissions: newPermissions })
    }

    render() {

        let alerts = [];
        for (let i = 0; i < this.state.alerts.length; i++) {
            alerts.push(<Alert key={"error-" + i} onClose={() => this.removeAlert(i)} {...this.state.alerts[i]}/>)
        }

        let th = [];
        th.push(<th key={"th-method"}>Method</th>);
        th.push(<th key={"th-everyone"} className={"text-center"}>Everyone</th>);

        for (let groupId in this.state.groups) {
            if (this.state.groups.hasOwnProperty(groupId)) {
                let groupName = this.state.groups[groupId].name;
                let groupColor = this.state.groups[groupId].color;
                th.push(
                    <th key={"th-" + groupId} className={"text-center"}>
                        <span key={"group-" + groupId} className={"badge text-white"} style={{backgroundColor: groupColor}}>
                            {groupName}
                        </span>
                    </th>
                );
            }
        }

        let tr = [];
        for (let i = 0; i < this.state.permissions.length; i++) {
            let permission = this.state.permissions[i];
            let td = [];

            if (permission.description) {
                td.push(
                    <td>
                        <ReactTooltip id={"tooltip-" + i} />
                        { permission.method }
                        <Icon icon={"info-circle"} className={"text-info float-right"}
                              data-tip={permission.description} data-place={"right"} data-type={"info"}
                              data-effect={"solid"} data-for={"tooltip-" + i} />
                    </td>
                );
            } else {
                td.push(
                    <td>
                        <ReactTooltip id={"tooltip-" + i} />
                        <input type={"text"} maxLength={32} value={this.state.permissions[i].method}
                            onChange={(e) => this.onChangeMethod(e, i)} />
                        <Icon icon={"trash"} className={"text-danger float-right"}
                              data-tip={"Delete"} data-place={"right"} data-type={"error"}
                              data-effect={"solid"} data-for={"tooltip-" + i}
                              onClick={() => this.onDeletePermission(i)} style={{cursor: "pointer"}} />
                    </td>
                );
            }

            td.push(
                <td key={"td-everyone"} className={"text-center"}>
                    <input type={"checkbox"} checked={this.state.permissions[i].groups.length === 0}
                           onChange={(e) => this.onChangePermission(e, i)}/>
                </td>
            );

            for (let groupId in this.state.groups) {
                if (this.state.groups.hasOwnProperty(groupId)) {
                    groupId = parseInt(groupId);
                    td.push(
                        <td key={"td-" + groupId} className={"text-center"}>
                            <input type={"checkbox"} checked={this.state.permissions[i].groups.includes(groupId)}
                                onChange={(e) => this.onChangePermission(e, i, groupId)}/>
                        </td>
                    );
                }
            }

            tr.push(<tr key={"permission-" + i}>{td}</tr>);
        }

        return <>
            <div className="content-header">
                <div className="container-fluid">
                    <div className="row mb-2">
                        <div className="col-sm-6">
                            <h1 className="m-0 text-dark">API Access Control</h1>
                        </div>
                        <div className="col-sm-6">
                            <ol className="breadcrumb float-sm-right">
                                <li className="breadcrumb-item"><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item"><Link to={"/admin/users"}>Users</Link></li>
                                <li className="breadcrumb-item active">Permissions</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                <div className={"row"}>
                    <div className={"col-lg-6 pl-5 pr-5"}>
                        {alerts}
                        <form onSubmit={(e) => e.preventDefault()}>
                            <table className={"table table-bordered table-hover dataTable dtr-inline"}>
                                <thead>
                                    <tr role={"row"}>
                                        {th}
                                    </tr>
                                </thead>
                                <tbody>
                                    {tr}
                                </tbody>
                            </table>

                            <div className={"mt-2"}>
                                <Link to={"/admin/users"} className={"btn btn-primary"}>
                                    <Icon icon={"arrow-left"}/>
                                    &nbsp;Back
                                </Link>
                                <button className={"btn btn-info ml-2"} onClick={() => this.onAddPermission()} disabled={this.state.isResetting || this.state.isSaving}>
                                    <Icon icon={"plus"}/>&nbsp;Add new Permission
                                </button>
                                <button className={"btn btn-secondary ml-2"} onClick={() => this.onResetPermissions()} disabled={this.state.isResetting || this.state.isSaving}>
                                    { this.state.isResetting ? <span>Resetting&nbsp;<Icon icon={"circle-notch"}/></span> : "Reset" }
                                </button>
                                <button className={"btn btn-success ml-2"} onClick={() => this.onSavePermissions()} disabled={this.state.isResetting || this.state.isSaving}>
                                    { this.state.isSaving ? <span>Saving&nbsp;<Icon icon={"circle-notch"}/></span> : "Save" }
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </>;
    }

    onAddPermission() {
        let newPermissions = this.state.permissions.slice();
        newPermissions.push({ method: "", groups: [], description: null });
        this.setState({ ...this.state, permissions: newPermissions })
    }

    onResetPermissions() {
        this.setState({ ...this.state, isResetting: true });
        this.fetchPermissions();
    }

    onSavePermissions() {
        this.setState({ ...this.state, isSaving: true });

        let permissions = [];
        for (let i = 0; i < this.state.permissions.length; i++) {
            let permission = this.state.permissions[i];
            permissions.push({ method: permission.method, groups: permission.groups });
        }

        this.parent.api.savePermissions(permissions).then((res) => {
            if (!res.success) {
                let alerts = this.state.alerts.slice();
                alerts.push({ message: res.msg, title: "Error saving permissions" });
                this.setState({...this.state, alerts: alerts, isSaving: false});
            } else {
                this.setState({...this.state, isSaving: false});
            }
        });
    }

    onDeletePermission(index) {
        if (index < 0 || index >= this.state.permissions.length) {
            return;
        }

        let newPermissions = this.state.permissions.slice();
        newPermissions.splice(index, 1);
        this.setState({ ...this.state, permissions: newPermissions })
    }

    onChangePermission(event, index, group = null) {
        if (index < 0 || index >= this.state.permissions.length) {
            return;
        }

        let isChecked = event.target.checked;
        let newPermissions = this.state.permissions.slice();
        if (group === null) {
            if (isChecked) {
                newPermissions[index].groups = [];
            } else {
                return;
            }
        } else {
            if (isChecked && !newPermissions[index].groups.includes(group)) {
                newPermissions[index].groups.push(group);
            } else if(!isChecked) {
                let indexOf = newPermissions[index].groups.indexOf(group);
                if (indexOf !== -1) {
                    newPermissions[index].groups.splice(indexOf, 1);
                } else {
                    return;
                }
            } else {
                return;
            }
        }

        this.setState({ ...this.state, permissions: newPermissions })
    }
};
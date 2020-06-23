import * as React from "react";
import Icon from "../elements/icon";
import {Link} from "react-router-dom";
import {getPeriodString} from "../global";
import Alert from "../elements/alert";
import ReactTooltip from "react-tooltip";

const TABLE_SIZE = 10;

export default class UserOverview extends React.Component {

    constructor(props) {
        super(props);
        this.parent = {
            showDialog: props.showDialog || function () { },
            api: props.api,
        };
        this.state = {
            loaded: false,
            users: {
                data: {},
                page: 1,
                pageCount: 1,
                totalCount: 0,
            },
            groups: {
                data: {},
                page: 1,
                pageCount: 1,
                totalCount: 0,
            },
            errors: [],
            rowCount: 0
        };
    }

    fetchGroups(page) {
        page = page || this.state.groups.page;
        this.setState({...this.state, groups: {...this.state.groups, data: {}, page: 1, totalCount: 0}});
        this.parent.api.fetchGroups(page, TABLE_SIZE).then((res) => {
            if (res.success) {
                this.setState({
                    ...this.state,
                    groups: {
                        data: res.groups,
                        pageCount: res.pageCount,
                        page: page,
                        totalCount: res.totalCount,
                    },
                    rowCount: Math.max(this.state.rowCount, Object.keys(res.groups).length)
                });
            } else {
                let errors = this.state.errors.slice();
                errors.push({title: "Error fetching groups", message: res.msg});
                this.setState({
                    ...this.state,
                    errors: errors
                });
            }
            if (!this.state.loaded) {
                this.fetchUsers(1)
            }
        });
    }

    fetchUsers(page) {
        page = page || this.state.users.page;
        this.setState({...this.state, users: {...this.state.users, data: {}, pageCount: 1, totalCount: 0}});
        this.parent.api.fetchUsers(page, TABLE_SIZE).then((res) => {
            if (res.success) {
                this.setState({
                    ...this.state,
                    loaded: true,
                    users: {
                        data: res.users,
                        pageCount: res.pageCount,
                        page: page,
                        totalCount: res.totalCount,
                    },
                    rowCount: Math.max(this.state.rowCount, Object.keys(res.users).length)
                });
            } else {
                let errors = this.state.errors.slice();
                errors.push({title: "Error fetching users", message: res.msg});
                this.setState({
                    ...this.state,
                    loaded: true,
                    errors: errors
                });
            }
        });
    }

    componentDidMount() {
        this.setState({...this.state, loaded: false});
        this.fetchGroups(1);
    }

    removeError(i) {
        if (i >= 0 && i < this.state.errors.length) {
            let errors = this.state.errors.slice();
            errors.splice(i, 1);
            this.setState({...this.state, errors: errors});
        }
    }

    render() {

        if (!this.state.loaded) {
            return <div className={"text-center mt-4"}>
                <h3>Loading…&nbsp;<Icon icon={"spinner"}/></h3>
            </div>
        }

        let errors = [];
        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.errors[i]}/>)
        }

        return <>
            <div className="content-header">
                <div className="container-fluid">
                    <div className="row mb-2">
                        <div className="col-sm-6">
                            <h1 className="m-0 text-dark">Users & Groups</h1>
                        </div>
                        <div className="col-sm-6">
                            <ol className="breadcrumb float-sm-right">
                                <li className="breadcrumb-item"><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item active">Users</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                {errors}
                <div className={"content-fluid"}>
                    <div className={"row"}>
                        <div className={"col-lg-6"}>
                            {this.createUserCard()}
                        </div>
                        <div className={"col-lg-6"}>
                            {this.createGroupCard()}
                        </div>
                    </div>
                </div>
            </div>
            <ReactTooltip />
        </>;
    }

    createUserCard() {

        let userRows = [];
        for (let uid in this.state.users.data) {
            if (!this.state.users.data.hasOwnProperty(uid)) {
                continue;
            }

            let user = this.state.users.data[uid];
            let groups = [];

            for (let groupId in user.groups) {
                if (user.groups.hasOwnProperty(groupId)) {
                    let groupName = user.groups[groupId].name;
                    let groupColor = user.groups[groupId].color;
                    groups.push(
                        <span key={"group-" + groupId} className={"mr-1 badge text-white"} style={{backgroundColor: groupColor}}>
                            {groupName}
                        </span>
                    );
                }
            }

            userRows.push(
                <tr key={"user-" + uid}>
                    <td>{user.name}</td>
                    <td>{user.email}</td>
                    <td>{groups}</td>
                    <td>
                        <span data-effect={"solid"}
                              data-tip={user.registered_at}
                              data-place={"bottom"}>
                            {getPeriodString(user.registered_at)}
                        </span>
                    </td>
                    <td>
                        <Link to={"/admin/user/edit/" + uid} className={"text-reset"}>
                            <Icon icon={"pencil-alt"} data-effect={"solid"}
                                  data-tip={"Modify user details & group membership"}
                                  data-type={"info"} data-place={"right"}/>
                        </Link>
                    </td>
                </tr>
            );
        }

        while(userRows.length < this.state.rowCount) {
            userRows.push(
                <tr key={"empty-row-" + userRows.length}>
                    <td>&nbsp;</td>
                    <td/>
                    <td/>
                    <td/>
                    <td/>
                </tr>
            );
        }

        let pages = [];
        let previousDisabled = (this.state.users.page === 1 ? " disabled" : "");
        let nextDisabled = (this.state.users.page >= this.state.users.pageCount ? " disabled" : "");

        for (let i = 1; i <= this.state.users.pageCount; i++) {
            let active = (this.state.users.page === i ? " active" : "");
            pages.push(
                <li key={"page-" + i} className={"page-item" + active}>
                    <a className={"page-link"} href={"#"} onClick={() => { if (this.state.users.page !== i) this.fetchUsers(i) }}>
                        {i}
                    </a>
                </li>
            );
        }

        return <div className={"card"}>
            <div className={"card-header border-0"}>
                <h3 className={"card-title"}>Users</h3>
                <div className={"card-tools"}>
                    <Link href={"#"} className={"btn btn-tool btn-sm"} to={"/admin/user/add"} >
                        <Icon icon={"plus"}/>
                    </Link>
                    <a href={"#"} className={"btn btn-tool btn-sm"} onClick={() => this.fetchUsers()}>
                        <Icon icon={"sync"}/>
                    </a>
                </div>
            </div>
            <div className={"card-body table-responsive p-0"}>
                <table className={"table table-striped table-valign-middle"}>
                    <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Groups</th>
                        <th>Registered</th>
                        <th/>
                    </tr>
                    </thead>
                    <tbody>
                    {userRows}
                    </tbody>
                </table>
                <nav className={"row m-0"}>
                    <div className={"col-6 pl-3 pt-3 pb-3 text-muted"}>
                        Total: {this.state.users.totalCount}
                    </div>
                    <div className={"col-6 p-0"}>
                        <ul className={"pagination p-2 m-0 justify-content-end"}>
                            <li className={"page-item" + previousDisabled}>
                                <a className={"page-link"} href={"#"}
                                   onClick={() => this.fetchUsers(this.state.users.page - 1)}>
                                    Previous
                                </a>
                            </li>
                            {pages}
                            <li className={"page-item" + nextDisabled}>
                                <a className={"page-link"} href={"#"}
                                   onClick={() => this.fetchUsers(this.state.users.page + 1)}>
                                    Next
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>;
    }

    createGroupCard() {
        let groupRows = [];
        for (let uid in this.state.groups.data) {
            if (!this.state.groups.data.hasOwnProperty(uid)) {
                continue;
            }

            let group = this.state.groups.data[uid];

            groupRows.push(
                <tr key={"group-" + uid}>
                    <td>{group.name}</td>
                    <td className={"text-center"}>{group.memberCount}</td>
                    <td>
                        <span className={"badge text-white mr-1"} style={{backgroundColor: group.color, fontFamily: "monospace"}}>
                            {group.color}
                        </span>
                    </td>
                </tr>
            );
        }

        while(groupRows.length < this.state.rowCount) {
            groupRows.push(
                <tr key={"empty-row-" + groupRows.length}>
                    <td>&nbsp;</td>
                    <td/>
                    <td/>
                </tr>
            );
        }

        let pages = [];
        let previousDisabled = (this.state.groups.page === 1 ? " disabled" : "");
        let nextDisabled = (this.state.groups.page >= this.state.groups.pageCount ? " disabled" : "");

        for (let i = 1; i <= this.state.groups.pageCount; i++) {
            let active = (this.state.groups.page === i ? " active" : "");
            pages.push(
                <li key={"page-" + i} className={"page-item" + active}>
                    <a className={"page-link"} href={"#"} onClick={() => { if (this.state.groups.page !== i) this.fetchGroups(i) }}>
                        {i}
                    </a>
                </li>
            );
        }

        return <div className={"card"}>
            <div className={"card-header border-0"}>
                <h3 className={"card-title"}>Groups</h3>
                <div className={"card-tools"}>
                    <Link href={"#"} className={"btn btn-tool btn-sm"} to={"/admin/users/addgroup"} >
                        <Icon icon={"plus"}/>
                    </Link>
                    <a href={"#"} className={"btn btn-tool btn-sm"} onClick={() => this.fetchGroups()}>
                        <Icon icon={"sync"}/>
                    </a>
                </div>
            </div>
            <div className={"card-body table-responsive p-0"}>
                <table className={"table table-striped table-valign-middle"}>
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th className={"text-center"}>Members</th>
                        <th>Color</th>
                    </tr>
                    </thead>
                    <tbody>
                    {groupRows}
                    </tbody>
                </table>
                <nav className={"row m-0"}>
                    <div className={"col-6 pl-3 pt-3 pb-3 text-muted"}>
                        Total: {this.state.groups.totalCount}
                    </div>
                    <div className={"col-6 p-0"}>
                        <ul className={"pagination p-2 m-0 justify-content-end"}>
                            <li className={"page-item" + previousDisabled}>
                                <a className={"page-link"} href={"#"}
                                   onClick={() => this.fetchGroups(this.state.groups.page - 1)}>
                                    Previous
                                </a>
                            </li>
                            {pages}
                            <li className={"page-item" + nextDisabled}>
                                <a className={"page-link"} href={"#"}
                                   onClick={() => this.fetchGroups(this.state.groups.page + 1)}>
                                    Next
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>;
    }
}
import * as React from "react";
import Icon from "./icon";
import {Link} from "react-router-dom";
import {getPeriodString} from "./global";

export default class UserOverview extends React.Component {

    constructor(props) {
        super(props);
        this.parent = {
            showDialog: props.showDialog || function() {},
            api: props.api,
        };
        this.state = {
            loaded: false,
            users: {
                data: {},
                page: 1,
                pageCount: 1
            },
            groups: {
                data: {},
                page: 1,
                pageCount: 1
            },
            errors: []
        };
    }

    fetchUsers(page) {
        page = page || this.state.users.page;
        this.setState({ ...this.state, users: { ...this.state.users, data: { }, pageCount: 1 } });
        this.parent.api.fetchUsers(page).then((res) => {
            if (res.success) {
                this.setState({
                    ...this.state,
                    loaded: true,
                    users: {
                        data: res.users,
                        pageCount: res.pageCount,
                        page: page
                    }
                });
            } else {
                this.setState({
                    ...this.state,
                    loaded: true,
                    errors: this.state.errors.slice().push(res.msg)
                });
            }
        });
    }

    componentDidMount() {
        this.setState({ ...this.state, loaded: false });
        this.fetchUsers(1);
    }

    render() {

        if (!this.state.loaded) {
            return <div className={"text-center mt-4"}>
                <h3>Loading…&nbsp;<Icon icon={"spinner"}/></h3>
            </div>
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
                <div className={"content-fluid"}>
                    <div className={"row"}>
                        <div className={"col-lg-6"}>
                            { this.createUserCard() }
                        </div>
                    </div>
                </div>
            </div>
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
                    let groupName = user.groups[groupId];
                    let color = (groupId === "1" ? "danger" : "secondary");
                    groups.push(<span key={"group-" + groupId} className={"mr-1 badge badge-" + color}>{groupName}</span>);
                }
            }

            userRows.push(
                <tr key={"user-" + uid}>
                    <td>{user.name}</td>
                    <td>{user.email}</td>
                    <td>{groups}</td>
                    <td>{getPeriodString(user.registered_at)}</td>
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
                  <a className={"page-link"} href={"#"} onClick={() => this.fetchUsers(i)}>
                      {i}
                  </a>
              </li>
            );
        }

        return <div className={"card"}>
            <div className={"card-header border-0"}>
                <h3 className={"card-title"}>Users</h3>
                <div className={"card-tools"}>
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
                        </tr>
                    </thead>
                    <tbody>
                      { userRows }
                    </tbody>
                </table>
                <nav aria-label={""}>
                    <ul className={"pagination p-2 m-0 justify-content-end"}>
                        <li className={"page-item" + previousDisabled}>
                          <a className={"page-link"} href={"#"} onClick={() => this.fetchUsers(this.state.users.page - 1)}>
                              Previous
                          </a>
                        </li>
                        { pages }
                        <li className={"page-item" + nextDisabled}>
                            <a className={"page-link"} href={"#"} onClick={() => this.fetchUsers(this.state.users.page + 1)}>
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>;
    }
}
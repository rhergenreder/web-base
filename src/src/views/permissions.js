import * as React from "react";
import {Link} from "react-router-dom";
import Icon from "../elements/icon";

export default class PermissionSettings extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            alerts: [],
            permissions: [],
            groups: {}
        }
    }

    render() {
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
                        <form>
                            <Link to={"/admin/users"} className={"btn btn-info mt-2 mr-2"}>
                                <Icon icon={"arrow-left"}/>
                                &nbsp;Back
                            </Link>
                        </form>
                    </div>
                </div>
            </div>
        </>;
    }
};
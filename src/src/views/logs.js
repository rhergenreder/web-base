import * as React from "react";
import {Link} from "react-router-dom";

export default class Logs extends React.Component {

    constructor(props) {
        super(props);
    }

    render() {
        return <>
            <div className="content-header">
                <div className="container-fluid">
                    <div className="row mb-2">
                        <div className="col-sm-6">
                            <h1 className="m-0 text-dark">Logs & Notifications</h1>
                        </div>
                        <div className="col-sm-6">
                            <ol className="breadcrumb float-sm-right">
                                <li className="breadcrumb-item"><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item active">Logs</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                <div className={"content-fluid"}>
                    <div className={"row"}>
                        <div className={"col-lg-6"}>

                        </div>
                        <div className={"col-lg-6"}>

                        </div>
                    </div>
                </div>
            </div>
        </>;
    }
}
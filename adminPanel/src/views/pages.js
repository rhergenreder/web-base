import * as React from "react";
import Alert from "../elements/alert";
import {Link} from "react-router-dom";
import Icon from "../elements/icon";
import ReactTooltip from "react-tooltip";
import Select from 'react-select';

export default class PageOverview extends React.Component {

    constructor(props) {
        super(props);
        this.parent = {
            api: props.api
        };

        this.state = {
            routes: [],
            errors: [],
            isResetting: false,
            isSaving: false
        };

        this.optionMap = {
            "redirect_temporary": "Redirect Temporary",
            "redirect_permanently": "Redirect Permanently",
            "static": "Serve Static",
            "dynamic": "Load Dynamic",
        };

        this.options = [];
        for (let key in this.optionMap) {
            this.options.push(this.buildOption(key));
        }
    }

    buildOption(key) {
        if (typeof key === 'object' && key.hasOwnProperty("key") && key.hasOwnProperty("label")) {
            return key;
        } else if (typeof key === 'string') {
            return { value: key, label: this.optionMap[key] };
        } else {
            return this.options[key];
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
        this.fetchRoutes()
    }

    render() {

        let errors = [];
        let rows = [];

        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.errors[i]}/>)
        }

        const inputStyle = { fontFamily: "Courier", paddingTop: "14px" };

        for (let i = 0; i <  this.state.routes.length; i++) {
            let route = this.state.routes[i];
            rows.push(
                <tr key={"route-" + i}>
                    <td className={"align-middle"}>
                        <input type={"text"} maxLength={128} style={inputStyle} className={"form-control"}
                               value={route.request} onChange={(e) => this.changeRequest(i, e)} />
                    </td>
                    <td className={"text-center"}>
                        <Select options={this.options} value={this.buildOption(route.action)} onChange={(selectedOption) => this.changeAction(i, selectedOption)} />
                    </td>
                    <td className={"align-middle"}>
                        <input type={"text"} maxLength={128} style={inputStyle} className={"form-control"}
                               value={route.target} onChange={(e) => this.changeTarget(i, e)} />
                    </td>
                    <td className={"align-middle"}>
                        <input type={"text"} maxLength={64} style={inputStyle} className={"form-control"}
                               value={route.extra} onChange={(e) => this.changeExtra(i, e)} />
                    </td>
                    <td className={"text-center"}>
                        <input
                            type={"checkbox"}
                            checked={route.active === 1}
                            onChange={(e) => this.changeActive(i, e)} />
                    </td>
                    <td>
                        <ReactTooltip id={"delete-" + i} />
                        <Icon icon={"trash"} style={{color: "red", cursor: "pointer"}}
                              data-tip={"Click to delete this route"}
                              data-type={"warning"} data-place={"right"}
                              data-for={"delete-" + i} data-effect={"solid"}
                              onClick={() => this.removeRoute(i)}/>
                    </td>
                </tr>
            );
        }

        return <>
            <div className={"content-header"}>
                <div className={"container-fluid"}>
                    <div className={"row mb-2"}>
                        <div className="col-sm-6">
                            <h1 className="m-0 text-dark">Routes & Pages</h1>
                        </div>
                        <div className="col-sm-6">
                            <ol className="breadcrumb float-sm-right">
                                <li className="breadcrumb-item"><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item active">Pages</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                {errors}
                <div className={"content-fluid"}>
                    <div className={"row"}>
                        <div className={"col-lg-10 col-12"}>
                            <table className={"table"}>
                                <thead className={"thead-dark"}>
                                    <tr>
                                        <th>
                                            Request&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#17a2b8"}}
                                                  data-tip={"The request, the user is making. Can also be interpreted as a regular expression."}
                                                  data-type={"info"} data-place={"bottom"}/>
                                        </th>
                                        <th style={{minWidth: "200px"}}>
                                            Action&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#17a2b8"}}
                                                  data-tip={"The action to be taken"}
                                                  data-type={"info"} data-place={"bottom"}/>
                                        </th>
                                        <th>
                                            Target&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#17a2b8"}}
                                                  data-tip={"Any URL if action is redirect or static. Path to a class inheriting from Document, " +
                                                  "if dynamic is chosen"}
                                                  data-type={"info"} data-place={"bottom"}/>
                                        </th>
                                        <th>
                                            Extra&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#17a2b8"}}
                                                  data-tip={"If action is dynamic, a view name can be entered here, otherwise leave empty."}
                                                  data-type={"info"} data-place={"bottom"}/>
                                        </th>
                                        <th className={"text-center"}>
                                            Active&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#17a2b8"}}
                                                  data-tip={"True, if the route is currently active."}
                                                  data-type={"info"} data-place={"bottom"}/>
                                        </th>
                                        <th/>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows}
                                </tbody>
                            </table>
                            <div>
                                <button className={"btn btn-info"} onClick={() => this.onAddRoute()} disabled={this.state.isResetting || this.state.isSaving}>
                                    <Icon icon={"plus"}/>&nbsp;Add new Route
                                </button>
                                <button className={"btn btn-secondary ml-2"} onClick={() => this.onResetRoutes()} disabled={this.state.isResetting || this.state.isSaving}>
                                    { this.state.isResetting ? <span>Resetting&nbsp;<Icon icon={"circle-notch"}/></span> : "Reset" }
                                </button>
                                <button className={"btn btn-success ml-2"} onClick={() => this.onSaveRoutes()} disabled={this.state.isResetting || this.state.isSaving}>
                                    { this.state.isSaving ? <span>Saving&nbsp;<Icon icon={"circle-notch"}/></span> : "Save" }
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <ReactTooltip data-effect={"solid"}/>
        </>
    }

    onResetRoutes() {
        this.setState({ ...this.state, isResetting: true });
        this.fetchRoutes();
    }

    onSaveRoutes() {
        this.setState({ ...this.state, isSaving: true });

        let routes = [];
        for (let i = 0; i < this.state.routes.length; i++) {
            let route = this.state.routes[i];
            routes.push({
                request: route.request,
                action: typeof route.action === 'object' ? route.action.value : route.action,
                target: route.target,
                extra: route.extra ?? "",
                active: route.active === 1
            });
        }

        this.parent.api.saveRoutes(routes).then((res) => {
            if (res.success) {
                this.setState({...this.state, isSaving: false});
            } else {
                let errors = this.state.errors.slice();
                errors.push({ title: "Error saving routes", message: res.msg });
                this.setState({...this.state, errors: errors, isSaving: false});
            }
        });
    }

    changeRoute(index, key, value) {
        if (index < 0 || index >= this.state.routes.length)
            return;

        let routes = this.state.routes.slice();
        routes[index][key] = value;
        this.setState({ ...this.state, routes: routes });
    }

    removeRoute(index) {
        if (index < 0 || index >= this.state.routes.length)
            return;
        let routes = this.state.routes.slice();
        routes.splice(index, 1);
        this.setState({ ...this.state, routes: routes });
    }


    onAddRoute() {
        let routes = this.state.routes.slice();
        routes.push({ request: "", action: "dynamic", target: "", extra: "", active: 1 });
        this.setState({ ...this.state, routes: routes });
    }

    changeAction(index, selectedOption) {
        this.changeRoute(index, "action", selectedOption);
    }

    changeActive(index, e) {
        this.changeRoute(index, "active", e.target.checked ? 1 : 0);
    }

    changeRequest(index, e) {
        this.changeRoute(index, "request", e.target.value);
    }

    changeTarget(index, e) {
        this.changeRoute(index, "target", e.target.value);
    }

    changeExtra(index, e) {
        this.changeRoute(index, "extra", e.target.value);
    }

    fetchRoutes() {
        this.parent.api.getRoutes().then((res) => {
            if (res.success) {
                this.setState({...this.state, routes: res.routes, isResetting: false});
                ReactTooltip.rebuild();
            } else {
                let errors = this.state.errors.slice();
                errors.push({ title: "Error fetching routes", message: res.msg });
                this.setState({...this.state, errors: errors, isResetting: false});
            }
        });
    }
}
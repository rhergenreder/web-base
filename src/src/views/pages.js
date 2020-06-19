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
            errors: []
        };

        this.options = {
            "redirect_temporary": "Redirect Temporary",
            "redirect_permanently": "Redirect Permanently",
            "static": "Serve Static",
            "dynamic": "Load Dynamic",
        };
    }

    buildOption(key) {
        if (typeof key === 'object' && key.hasOwnProperty("key") && key.hasOwnProperty("label")) {
            return key;
        } else {
            return { value: key, label: this.options[key] };
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
        this.parent.api.getRoutes().then((res) => {
            if (res.success) {
                this.setState({...this.state, routes: res.routes});
            } else {
                let errors = this.state.errors.slice();
                errors.push({ title: "Error fetching routes", message: res.msg });
                this.setState({...this.state, errors: errors});
            }
        });
    }

    render() {

        let errors = [];
        let rows = [];

        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.errors[i]}/>)
        }

        let options = [];
        for (let key in Object.keys(this.options)) {
            options.push(this.buildOption(key));
        }

        for (let i = 0; i <  this.state.routes.length; i++) {
            let route = this.state.routes[i];
            rows.push(
                <tr key={"route-" + i}>
                    <td className={"align-middle"}>{route.request}</td>
                    <td className={"text-center"}>
                        <Select options={options} value={this.buildOption(route.action)} onChange={(selectedOption) => this.changeAction(i, selectedOption)} />
                    </td>
                    <td className={"align-middle"}>{route.target}</td>
                    <td className={"align-middle"}>{route.extra}</td>
                    <td className={"text-center"}>
                        <input
                            type={"checkbox"}
                            checked={route.active === 1}
                            onChange={(e) => this.changeActive(i, e)} />
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
                        <div className={"col-lg-8 col-12"}>
                            <table className={"table"}>
                                <thead className={"thead-dark"}>
                                    <tr>
                                        <td>
                                            Request&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#0069d9"}}
                                                  data-tip={"The request, the user is making. Can also be interpreted as a regular expression."}
                                                  data-type={"info"} data-place={"bottom"} data-effect={"solid"}/>
                                        </td>
                                        <td style={{minWidth: "250px"}}>
                                            Action&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#0069d9"}}
                                                  data-tip={"The action to be taken"}
                                                  data-type={"info"} data-place={"bottom"} data-effect={"solid"}/>
                                        </td>
                                        <td>
                                            Target&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#0069d9"}}
                                                  data-tip={"Any URL if action is redirect or static. Path to a class inheriting from Document, " +
                                                  "if dynamic is chosen"}
                                                  data-type={"info"} data-place={"bottom"} data-effect={"solid"}/>
                                        </td>
                                        <td>
                                            Extra&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#0069d9"}}
                                                  data-tip={"If action is dynamic, a view name can be entered here, otherwise leave empty."}
                                                  data-type={"info"} data-place={"bottom"} data-effect={"solid"}/>
                                        </td>
                                        <td className={"text-center"}>
                                            Active&nbsp;
                                            <Icon icon={"question-circle"} style={{"color": "#0069d9"}}
                                                  data-tip={"True, if the route is currently active."}
                                                  data-type={"info"} data-place={"bottom"} data-effect={"solid"}/>
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <ReactTooltip/>
        </>
    }

    changeAction(index, selectedOption) {
        if (index < 0 || index >= this.state.routes.length)
            return;

        let routes = this.state.routes.slice();
        routes[index].action = selectedOption;
        this.setState({
            ...this.state,
            routes: routes
        });
    }

    changeActive(index, e) {
        if (index < 0 || index >= this.state.routes.length)
            return;

        let routes = this.state.routes.slice();
        routes[index].active = e.target.checked ? 1 : 0;
        this.setState({
            ...this.state,
            routes: routes
        });
    }
}
import * as React from "react";
import {Link} from "react-router-dom";
import Icon from "../elements/icon";
import { Bar } from 'react-chartjs-2';
import {Collapse} from 'react-collapse';
import moment from 'moment';
import Alert from "../elements/alert";
import humanReadableSize from "../global";

export default class Overview extends React.Component {

    constructor(props) {
        super(props);

        this.parent = {
            showDialog: props.showDialog,
            api: props.api,
        };

        this.state = {
            chartVisible : true,
            statusVisible : true,
            userCount: 0,
            notificationCount: 0,
            visitors: { },
            server: { load_avg: ["Unknown"] },
            errors: []
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
        this.parent.api.getStats().then((res) => {
            if(!res.success) {
                let errors = this.state.errors.slice();
                errors.push({ message: res.msg, title: "Error fetching Stats" });
                this.setState({ ...this.state, errors: errors });
            } else {
                this.setState({
                    ...this.state,
                    userCount: res.userCount,
                    pageCount: res.pageCount,
                    visitors: res.visitors,
                    server: res.server
                });
            }
        });
    }

    render() {

        const colors = [
            '#ff4444', '#ffbb33', '#00C851', '#33b5e5',
            '#ff4444', '#ffbb33', '#00C851', '#33b5e5',
            '#ff4444', '#ffbb33', '#00C851', '#33b5e5'
        ];

        let data = new Array(12).fill(0);
        let visitorCount = 0;
        for (let date in this.state.visitors) {
            let month = parseInt(date) % 100 - 1;
            if (month >= 0 && month < 12) {
                let count = parseInt(this.state.visitors[date]);
                data[month] = count;
                visitorCount += count;
            }
        }

        let chartOptions = {};
        let chartData = {
            labels: moment.monthsShort(),
            datasets: [{
                label: 'Unique Visitors ' + moment().year(),
                borderWidth: 1,
                data: data,
                backgroundColor: colors,
            }]
        };

        let errors = [];
        for (let i = 0; i < this.state.errors.length; i++) {
            errors.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.errors[i]}/>)
        }

        let loadAvg = this.state.server.load_avg;
        if (Array.isArray(this.state.server.load_avg)) {
            loadAvg = this.state.server.load_avg.join(" ");
        }

        return <>
            <div className={"content-header"}>
                <div className={"container-fluid"}>
                    <div className={"row mb-2"}>
                        <div className={"col-sm-6"}>
                            <h1 className={"m-0 text-dark"}>Dashboard</h1>
                        </div>
                        <div className={"col-sm-6"}>
                            <ol className={"breadcrumb float-sm-right"}>
                                <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <section className={"content"}>
                <div className={"container-fluid"}>
                    {errors}
                    <div className={"row"}>
                        <div className={"col-lg-3 col-6"}>
                            <div className="small-box bg-info">
                                <div className={"inner"}>
                                    <h3>{this.state.userCount}</h3>
                                    <p>Users registered</p>
                                </div>
                                <div className="icon">
                                    <Icon icon={"users"} />
                                </div>
                                <Link to={"/admin/users"} className="small-box-footer">More info <Icon icon={"arrow-circle-right"}/></Link>
                            </div>
                        </div>
                        <div className={"col-lg-3 col-6"}>
                            <div className={"small-box bg-success"}>
                                <div className={"inner"}>
                                    <h3>{this.state.pageCount}</h3>
                                    <p>Routes & Pages</p>
                                </div>
                                <div className="icon">
                                    <Icon icon={"copy"} />
                                </div>
                                <Link to={"/admin/pages"} className="small-box-footer">More info <Icon icon={"arrow-circle-right"}/></Link>
                            </div>
                        </div>
                        <div className={"col-lg-3 col-6"}>
                            <div className={"small-box bg-warning"}>
                                <div className={"inner"}>
                                    <h3>{this.props.notifications.length}</h3>
                                    <p>new Notifications</p>
                                </div>
                                <div className={"icon"}>
                                    <Icon icon={"bell"} />
                                </div>
                                <Link to={"/admin/logs"} className="small-box-footer">More info <Icon icon={"arrow-circle-right"}/></Link>
                            </div>
                        </div>
                        <div className={"col-lg-3 col-6"}>
                            <div className={"small-box bg-danger"}>
                                <div className={"inner"}>
                                    <h3>{visitorCount}</h3>
                                    <p>Unique Visitors</p>
                                </div>
                                <div className="icon">
                                    <Icon icon={"chart-line"} />
                                </div>
                                <Link to={"/admin/statistics"} className="small-box-footer">More info <Icon icon={"arrow-circle-right"}/></Link>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="row">
                    <div className="col-lg-6 col-12">
                        <div className="card card-info">
                            <div className="card-header">
                                <h3 className="card-title">Unique Visitors this year</h3>
                                <div className="card-tools">
                                    <button type="button" className={"btn btn-tool"} onClick={(e) => {
                                        e.preventDefault();
                                        this.setState({ ...this.state, chartVisible: !this.state.chartVisible });
                                    }}>
                                        <Icon icon={"minus"} />
                                    </button>
                                </div>
                            </div>
                            <Collapse isOpened={this.state.chartVisible}>
                                <div className="card-body">
                                    <div className="chart">
                                        <Bar data={chartData} options={chartOptions} />
                                    </div>
                                </div>
                            </Collapse>
                        </div>
                    </div>
                    <div className="col-lg-6 col-12">
                        <div className="card card-warning">
                            <div className="card-header">
                                <h3 className="card-title">Server Status</h3>
                                <div className="card-tools">
                                    <button type="button" className={"btn btn-tool"} onClick={(e) => {
                                        e.preventDefault();
                                        this.setState({ ...this.state, statusVisible: !this.state.statusVisible });
                                    }}>
                                        <Icon icon={"minus"} />
                                    </button>
                                </div>
                            </div>
                            <Collapse isOpened={this.state.statusVisible}>
                                <div className="card-body">
                                    <ul className={"list-unstyled"}>
                                        <li><b>Version</b>: {this.state.server.version}</li>
                                        <li><b>Server</b>: {this.state.server.server}</li>
                                        <li><b>Memory Usage</b>: {humanReadableSize(this.state.server.memory_usage)}</li>
                                        <li><b>Load Average</b>: { loadAvg }</li>
                                        <li><b>Database</b>: { this.state.server.database  }</li>
                                        <li><b>Mail</b>: { this.state.server.mail === true
                                            ?  <span>OK<Icon icon={""} className={"ml-2"}/></span>
                                            :  <Link to={"/admin/settings"}>Not configured</Link>}</li>
                                    </ul>
                                </div>
                            </Collapse>
                        </div>
                    </div>
                </div>
            </section>
        </>
    }
}
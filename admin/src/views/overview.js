import * as React from "react";
import {Link} from "react-router-dom";
import Icon from "../elements/icon";
import { Bar } from 'react-chartjs-2';
import {Collapse} from 'react-collapse';
import moment from 'moment';

export default class Overview extends React.Component {

    constructor(props) {
        super(props);
        this.parent = {
            showDialog: props.showDialog,
            notifications: props.notification,
            api: props.api,
        };

        this.state = {
            chartVisible : true,
        }
    }

    fetchStats() {

    }

    render() {

        let userCount = 0;
        let notificationCount = 0;
        let pageCount = 0;
        let visitorCount = 0;

        const colors = [
            '#ff4444', '#ffbb33', '#00C851', '#33b5e5',
            '#ff4444', '#ffbb33', '#00C851', '#33b5e5',
            '#ff4444', '#ffbb33', '#00C851', '#33b5e5'
        ];

        let chartOptions = {};
        let chartData = {
            labels: moment.monthsShort(),
            datasets: [{
                label: 'Unique Visitors ' + moment().year(),
                borderWidth: 1,
                data: [ 10, 20, 30, 0, 15, 5, 40, 100, 6, 3, 10, 20 ],
                backgroundColor: colors,
            }]
        };

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
                    <div className={"row"}>
                        <div className={"col-lg-3 col-6"}>
                            <div className="small-box bg-info">
                                <div className={"inner"}>
                                    <h3>{userCount}</h3>
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
                                    <h3>{pageCount}</h3>
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
                                    <h3>{notificationCount}</h3>
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
                                        <div className="chartjs-size-monitor">
                                            <div className="chartjs-size-monitor-expand">
                                                <div/>
                                            </div>
                                            <div className="chartjs-size-monitor-shrink">
                                                <div/>
                                            </div>
                                        </div>
                                        <Bar data={chartData} options={chartOptions} />
                                    </div>
                                </div>
                            </Collapse>
                        </div>
                    </div>
                </div>
            </section>
        </>
    }
}
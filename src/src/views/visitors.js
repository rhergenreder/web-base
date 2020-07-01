import {Link} from "react-router-dom";
import * as React from "react";
import Alert from "../elements/alert";
import moment from 'moment'
import {Bar} from "react-chartjs-2";
import DatePicker from "react-datepicker";

import "react-datepicker/dist/react-datepicker.css";


export default class Visitors extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            alerts: [],
            date: new Date(),
            type: 'monthly',
            visitors: { }
        };

        this.parent = {
            api: props.api,
        }
    }

    componentDidMount() {
        this.fetchData(this.state.type, this.state.date);
    }

    fetchData(type, date) {
        this.setState({...this.state, type: type, date: date });
        this.parent.api.getVisitors(type, moment(date).format("YYYY-MM-DD")).then((res) => {
            if(!res.success) {
                let alerts = this.state.alerts.slice();
                alerts.push({ message: res.msg, title: "Error fetching Visitor Statistics" });
                this.setState({ ...this.state, alerts: alerts });
            } else {
                this.setState({
                    ...this.state,
                    visitors: res.visitors
                });
            }
        });
    }

    removeError(i) {
        if (i >= 0 && i < this.state.alerts.length) {
            let alerts = this.state.alerts.slice();
            alerts.splice(i, 1);
            this.setState({...this.state, alerts: alerts});
        }
    }

    showData(type) {
        if (type === this.state.type) {
            return;
        }

        this.fetchData(type, this.state.date);
    }

    createLabels() {
        if (this.state.type === 'weekly') {
            return moment.weekdays();
        } else if(this.state.type === 'monthly') {
            const numDays = moment().daysInMonth();
            return Array.from(Array(numDays), (_, i) => i + 1);
        } else if(this.state.type === 'yearly') {
            return moment.monthsShort();
        } else {
            return [];
        }
    }

    createTitle() {
        if (this.state.type === 'weekly') {
            return "Week " + moment(this.state.date).week();
        } else if(this.state.type === 'monthly') {
            return moment(this.state.date).format('MMMM');
        } else if(this.state.type === 'yearly') {
            return moment(this.state.date).format('YYYY');
        } else {
            return "";
        }
    }

    fillData(data = []) {

        for (let date in this.state.visitors) {
            if (!this.state.visitors.hasOwnProperty(date)) {
                continue;
            }

            let parts = date.split("/");
            let count = parseInt(this.state.visitors[date]);

            if (this.state.type === 'weekly') {
                let day = moment(date).day();
                if (day >= 0 && day < 7) {
                    data[day] = count;
                }
            } else if(this.state.type === 'monthly') {
                let day = parseInt(parts[2]) - 1;
                if (day >= 0 && day < data.length) {
                    data[day] = count;
                }
            } else if(this.state.type === 'yearly') {
                let month = parseInt(parts[1]) - 1;
                if (month >= 0 && month < 12) {
                    data[month] = count;
                }
            }
        }
    }

    handleChange(date) {
        this.fetchData(this.state.type, date);
    }

    render() {

        let alerts = [];
        for (let i = 0; i < this.state.alerts.length; i++) {
            alerts.push(<Alert key={"error-" + i} onClose={() => this.removeError(i)} {...this.state.alerts[i]}/>)
        }

        const viewTypes = ["Weekly", "Monthly", "Yearly"];
        let viewOptions = [];
        for (let type of viewTypes) {
            let isActive = this.state.type === type.toLowerCase();
            viewOptions.push(
                <label key={"option-" + type.toLowerCase()} className={"btn btn-secondary" + (isActive ? " active" : "")}>
                    <input type="radio" autoComplete="off" defaultChecked={isActive} onClick={() => this.showData(type.toLowerCase())} />
                    {type}
                </label>
            );
        }

        const labels = this.createLabels();
        let data = new Array(labels.length).fill(0);
        this.fillData(data);

        let colors =  [ '#ff4444', '#ffbb33', '#00C851', '#33b5e5' ];
        const title = this.createTitle();

        while (colors.length < labels.length) {
            colors = colors.concat(colors);
        }

        let chartOptions = {};
        let chartData = {
            labels: labels,
            datasets: [{
                label: 'Unique Visitors ' + title,
                borderWidth: 1,
                data: data,
                backgroundColor: colors
            }],
            maintainAspectRatio: false
        };

        return <>
            <div className={"content-header"}>
                <div className={"container-fluid"}>
                    <div className={"row mb-2"}>
                        <div className={"col-sm-6"}>
                            <h1 className={"m-0 text-dark"}>Visitor Statistics</h1>
                        </div>
                        <div className={"col-sm-6"}>
                            <ol className={"breadcrumb float-sm-right"}>
                                <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className="breadcrumb-item active">Visitors</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <section className={"content"}>
                <div className={"container-fluid"}>
                    {alerts}
                    <div className={"row"}>
                        <div className={"col-4"}>
                            <p className={"mb-1 lead"}>Show data…</p>
                            <div className="btn-group btn-group-toggle" data-toggle="buttons">
                                {viewOptions}
                            </div>
                        </div>
                        <div className={"col-4"}>
                            <p className={"mb-1 lead"}>Select date…</p>
                            <DatePicker className={"text-center"} selected={this.state.date} onChange={(d) => this.handleChange(d)}
                                        showMonthYearPicker={this.state.type === "monthly"}
                                        showYearPicker={this.state.type === "yearly"} />
                        </div>
                    </div>
                    <div className={"row"}>
                        <div className={"col-12"}>
                            <div className="chart p-3">
                                <Bar data={chartData} options={chartOptions} height={100} />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </>
    }
}
import * as React from "react";
import {Link} from "react-router-dom";
import {format, getDaysInMonth} from "date-fns";

export default function Overview(props) {

    const today = new Date();
    const numDays = getDaysInMonth(today);

    let colors =  [ '#ff4444', '#ffbb33', '#00C851', '#33b5e5' ];
    while (colors.length < numDays) {
        colors = colors.concat(colors);
    }

    let data = new Array(numDays).fill(0);
    let visitorCount = 0;
    /*
    for (let date in this.state.visitors) {
        if (this.state.visitors.hasOwnProperty(date)) {
            let day = parseInt(date.split("/")[2]) - 1;
            if (day >= 0 && day < numDays) {
                let count = parseInt(this.state.visitors[date]);
                data[day] = count;
                visitorCount += count;
            }
        }
    }
     */

    let labels = Array.from(Array(numDays), (_, i) => i + 1);
    let chartOptions = {};
    let chartData = {
        labels: labels,
        datasets: [{
            label: 'Unique Visitors ' + format(today, "MMMM"),
            borderWidth: 1,
            data: data,
            backgroundColor: colors,
        }]
    };

    /*
    let loadAvg = this.state.server.load_avg;
    if (Array.isArray(this.state.server.load_avg)) {
        loadAvg = this.state.server.load_avg.join(" ");
    }
     */

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
        </section>
    </>
}

/*
export default class Overview extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            chartVisible : true,
            statusVisible : true,
            userCount: 0,
            notificationCount: 0,
            visitorsTotal: 0,
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
                    visitorsTotal: res.visitorsTotal,
                    server: res.server
                });
            }
        });
    }

    render() {


    }
}*/
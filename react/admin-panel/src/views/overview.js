import * as React from "react";
import {Link} from "react-router-dom";
import {format, getDaysInMonth} from "date-fns";
import {Collapse} from "react-collapse";
import {Bar} from "react-chartjs-2";
import {CircularProgress, Icon} from "@material-ui/core";
import {useCallback, useEffect, useState} from "react";

export default function Overview(props) {

    const [fetchStats, setFetchStats] = useState(true);
    const [stats, setStats] = useState(null);

    const onFetchStats = useCallback((force = false) => {
        if (force || fetchStats) {
            setFetchStats(false);
            props.api.getStats().then((res) => {
                if (res.success) {
                    setStats(res.data);
                } else {
                    props.showDialog("Error fetching stats: " + res.msg, "Error fetching stats");
                }
            });
        }
    }, [fetchStats]);

    useEffect(() => {
        onFetchStats();
    }, []);

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

    console.log(stats);

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
                                {stats ?
                                    <>
                                        <h3>{stats.userCount}</h3>
                                        <p>Users registered</p>
                                    </> : <CircularProgress variant={"determinate"} />
                                }
                            </div>
                            <div className="icon">
                                <Icon icon={"users"} />
                            </div>
                            <Link to={"/admin/users"} className="small-box-footer">
                                More info <Icon icon={"arrow-circle-right"}/>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </>
}

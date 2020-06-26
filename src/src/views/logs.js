import * as React from "react";
import {Link} from "react-router-dom";
import Icon from "../elements/icon";
import moment from 'moment';

export default class Logs extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            alerts: [],
            notifications: []
        };

        this.parent = {
            api : props.api,
            fetchNotifications: props.fetchNotifications,
        }
    }

    removeError(i) {
        if (i >= 0 && i < this.state.alerts.length) {
            let alerts = this.state.alerts.slice();
            alerts.splice(i, 1);
            this.setState({...this.state, alerts: alerts});
        }
    }

    componentDidMount() {
        this.parent.api.getNotifications(false).then((res) => {
            if (!res.success) {
                let alerts = this.state.alerts.slice();
                alerts.push({ message: res.msg, title: "Error fetching Notifications" });
                this.setState({ ...this.state, alerts: alerts });
            } else {
                this.setState({ ...this.state, notifications: res.notifications });
            }

            this.parent.api.markNotificationsSeen().then((res) => {
                if (!res.success) {
                    let alerts = this.state.alerts.slice();
                    alerts.push({ message: res.msg, title: "Error fetching Notifications" });
                    this.setState({ ...this.state, alerts: alerts });
                }

                this.parent.fetchNotifications();
            });
        });
    }

    render() {

        const colors = ["red", "green", "blue", "purple", "maroon"];

        let dates = { };
        for (let notification of this.state.notifications) {
            let day = moment(notification["created_at"]).format('ll');
            if (!dates.hasOwnProperty(day)) {
                dates[day] = [];
            }

            let icon = "bell";
            if (notification.type === "message") {
                icon = "envelope";
            } else if(notification.type === "warning") {
                icon = "exclamation-triangle";
            }

            dates[day].push({ ...notification, icon: icon, timestamp: notification["created_at"] });
        }

        let elements = [];
        for (let date in dates) {
            let color = colors[Math.floor(Math.random() * colors.length)];

            elements.push(
                <div className={"time-label"} key={"time-label-" + date}>
                    <span className={"bg-" + color}>{date}</span>
                </div>
            );

            for (let event of dates[date]) {
                let timeString = moment(event.timestamp).fromNow();
                elements.push(
                    <div>
                        <Icon icon={event.icon} className={"bg-" + color}/>
                        <div className="timeline-item">
                            <span className="time"><Icon icon={"clock"}/> {timeString}</span>
                            <h3 className="timeline-header">{event.title}</h3>
                            <div className="timeline-body">{event.message}</div>
                        </div>
                    </div>
                );
            }
        }

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
                <div className={"container-fluid"}>
                    <div className={"row"}>
                        <div className={"col-lg-8 col-12"}>
                            <div className="timeline">
                                <div className={"time-label"}>
                                    <span className={"bg-blue"}>Today</span>
                                </div>
                                {elements}
                                <div>
                                    <Icon icon={"clock"} className={"bg-gray"}/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>;
    }
}
import * as React from "react";
import Icon from "./icon";
import {useState} from "react";
import {getPeriodString} from "./global";

export default function Header(props) {

    const parent = {
        onChangeView: props.onChangeView || function() {},
        notifications: props.notifications || { },
    };

    function onChangeView(view) {
        parent.onChangeView(view);
    }

    const [dropdownVisible, showDropdown] = useState(false);
    const mailIcon = <Icon icon={"envelope"} type={"fas"} />;

    let notificationCount = Object.keys(parent.notifications).length;
    let notificationText  = "No new notifications";

    if(notificationCount === 1) {
        notificationText = "1 new notification";
    } else if(notificationCount > 1) {
        notificationText = notificationCount + " new notification";
    }

    let notificationItems = [];
    for (let uid in parent.notifications) {
        const notification = parent.notifications[uid];
        const createdAt = getPeriodString(notification.created_at);
        notificationItems.push(
            <a href="#" className={"dropdown-item"} key={"notification-" + uid}>
                {mailIcon}
                <span className={"ml-2"}>{notification.title}</span>
                <span className={"float-right text-muted text-sm"}>{createdAt}</span>
            </a>);
    }

    return (
        <nav className={"main-header navbar navbar-expand navbar-white navbar-light"}>

            {/*Left navbar links */}
            <ul className={"navbar-nav"}>
                <li className={"nav-item"}>
                    <a href={"#"} className={"nav-link"} data-widget={"pushmenu"} role={"button"}>
                        <Icon icon={"bars"}/>
                    </a>
                </li>
                <li className={"nav-item d-none d-sm-inline-block"}>
                    <a href={"#"} onClick={() => onChangeView("dashboard")} className={"nav-link"}>
                        Home
                    </a>
                </li>
            </ul>

            {/* SEARCH FORM */}
            <form className={"form-inline ml-3"}>
                <div className={"input-group input-group-sm"}>
                    <input className={"form-control form-control-navbar"} type={"search"} placeholder={"Search"} aria-label={"Search"} />
                    <div className={"input-group-append"}>
                        <button className={"btn btn-navbar"} type={"submit"}>
                            <Icon icon={"search"}/>
                        </button>
                    </div>
                </div>
            </form>

            {/* Right navbar links */}
            <ul className={"navbar-nav ml-auto"}>

                {/* Notifications Dropdown Menu */}
                <li className={"nav-item dropdown"} onClick={() => showDropdown(!dropdownVisible)}>
                    <a href={"#"} className={"nav-link"} data-toggle={"dropdown"}>
                        <Icon icon={"bell"} type={"far"} />
                        <span className={"badge badge-warning navbar-badge"}>
                            {notificationCount}
                        </span>
                    </a>
                    <div className={"dropdown-menu dropdown-menu-lg dropdown-menu-right " + (dropdownVisible ? " show" : "")}>
                        <span className={"dropdown-item dropdown-header"}>
                            {notificationText}
                        </span>
                        {notificationItems}
                        <div className={"dropdown-divider"} />
                        <a href={"#"} onClick={() => onChangeView("dashboard")} className={"dropdown-item dropdown-footer"}>See All Notifications</a>
                    </div>
                </li>
            </ul>
        </nav>
    )
}
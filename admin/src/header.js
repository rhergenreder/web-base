import * as React from "react";
import Icon from "./icon";

export default class Header extends React.Component {
    render() {

        let notificationCount = 0;
        let notificationText = "";

        return <nav className={"main-header navbar navbar-expand navbar-white navbar-light"}>

            {/*Left navbar links */}
            <ul className={"navbar-nav"}>
                <li className={"nav-item"}>
                    <a href={"#"} className={"nav-link"} data-widget={"pushmenu"} role={"button"}>
                        <Icon icon={"bars"}/>
                    </a>
                </li>
                <li className={"nav-item d-none d-sm-inline-block"}>
                    <a href={"#"} className={"nav-link"}>
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
                <li className={"nav-item dropdown"}>
                    <a href={"#"} className={"nav-link"} data-toggle={"dropdown"}>
                        <Icon class={"bell"} type={"far"} />
                        <span className={"badge badge-warning navbar-badge"}>
                            {notificationCount}
                        </span>
                    </a>
                    <div className={"dropdown-menu dropdown-menu-lg dropdown-menu-right"}>
                        <span className={"dropdown-item dropdown-header"}>
                            {notificationText}
                        </span>
                        <div className={"dropdown-divider"} />
                        <a href={"#"} className={"dropdown-item dropdown-footer"}>See All Notifications</a>
                    </div>
                </li>
            </ul>
        </nav>
    }
}
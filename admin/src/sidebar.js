import React from 'react';
import Icon from "./icon";
import {Link, NavLink} from "react-router-dom";

export default function Sidebar(props) {

    let parent = {
        showDialog: props.showDialog || function() {},
        api: props.api
    };

    function onLogout() {
        parent.api.logout().then(obj => {
            if (obj.success) {
                document.location = "/admin";
            } else {
                parent.showDialog("Error logging out: " + obj.msg, "Error logging out");
            }
        });
    }

    const menuItems = {
        "dashboard": {
            "name": "Dashboard",
            "icon": "tachometer-alt"
        },
        "users": {
            "name": "Users & Groups",
            "icon": "users"
        },
        "settings": {
            "name": "Settings",
            "icon": "tools"
        },
        "help": {
            "name": "Help",
            "icon": "question-circle"
        },
    };

    let numNotifications = Object.keys(props.notifications).length;
    if (numNotifications > 0) {
        if (numNotifications > 9) numNotifications = "9+";
        menuItems["dashboard"]["badge"] = { type: "warning", value: numNotifications };
    }

    let li = [];
    for (let id in menuItems) {
        let obj = menuItems[id];
        const badge = (obj.badge ? <span className={"right badge badge-" + obj.badge.type}>{obj.badge.value}</span> : <></>);

        li.push(
            <li key={id} className={"nav-item"}>
                <NavLink to={"/admin/" + id} className={"nav-link"} activeClassName={"active"}>
                    <Icon icon={obj.icon} classes={"nav-icon"} /><p>{obj.name}{badge}</p>
                </NavLink>
            </li>
        );
    }

    li.push(<li className={"nav-item"} key={"logout"}>
        <a href={"#"} onClick={() => onLogout()} className={"nav-link"}>
            <Icon icon={"arrow-left"} classes={"nav-icon"} />
            <p>Logout</p>
        </a>
    </li>);

    return (
        <aside className={"main-sidebar sidebar-dark-primary elevation-4"}>
            <Link href={"#"} className={"brand-link"} to={"/admin/dashboard"}>
                <img src={"/img/icons/logo.png"} alt={"Logo"} className={"brand-image img-circle elevation-3"} style={{opacity: ".8"}} />
                <span className={"brand-text font-weight-light ml-2"}>WebBase</span>
            </Link>

            <div className={"sidebar os-host os-theme-light os-host-overflow os-host-overflow-y os-host-resize-disabled os-host-scrollbar-horizontal-hidden os-host-transition"}>
                {/* IDK what this is */}
                <div className={"os-resize-observer-host"}>
                    <div className={"os-resize-observer observed"} style={{left: "0px", right: "auto"}}/>
                </div>
                <div className={"os-size-auto-observer"} style={{height: "calc(100% + 1px)", float: "left"}}>
                    <div className={"os-resize-observer observed"}/>
                </div>
                <div className={"os-content-glue"} style={{margin: "0px -8px"}}/>
                <div className={"os-padding"}>
                    <div className={"os-viewport os-viewport-native-scrollbars-invisible"} style={{right: "0px", bottom: "0px"}}>
                        <div className={"os-content"} style={{padding: "0px 0px", height: "100%", width: "100%"}}>

                            {/* LOGGED IN AS */}
                            <div className="user-panel mt-3 pb-3 mb-3 d-flex">
                                <div className="info">
                                    <a href="#" className="d-block">Logged in as: {parent.api.user.name}</a>
                                </div>
                            </div>

                            {/* SIDEBAR */}
                            <nav className={"mt-2"}>
                                <ul className={"nav nav-pills nav-sidebar flex-column"} data-widget={"treeview"} role={"menu"} data-accordion={"false"}>
                                    {li}
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    )
}

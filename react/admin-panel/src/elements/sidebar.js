import React, {useCallback, useContext} from 'react';
import {Link, NavLink} from "react-router-dom";
import Icon from "shared/elements/icon";
import {LocaleContext} from "shared/locale";

export default function Sidebar(props) {

    const api = props.api;
    const showDialog = props.showDialog;
    const {translate: L} = useContext(LocaleContext);

    const onLogout = useCallback(() => {
        api.logout().then(obj => {
            if (obj.success) {
                document.location = "/admin";
            } else {
                showDialog("Error logging out: " + obj.msg, "Error logging out");
            }
        });
    }, [api, showDialog]);

    const menuItems = {
        "dashboard": {
            "name": "admin.dashboard",
            "icon": "tachometer-alt"
        },
        "visitors": {
            "name": "admin.visitor_statistics",
            "icon": "chart-bar",
        },
        "users": {
            "name": "admin.users",
            "icon": "users"
        },
        "groups": {
            "name": "admin.groups",
            "icon": "users-cog"
        },
        "routes": {
            "name": "admin.page_routes",
            "icon": "copy",
        },
        "settings": {
            "name": "admin.settings",
            "icon": "tools"
        },
        "permissions": {
            "name": "admin.acl",
            "icon": "door-open"
        },
        "logs": {
            "name": "admin.logs",
            "icon": "file-medical-alt"
        },
        "help": {
            "name": "admin.help",
            "icon": "question-circle"
        },
    };
    
    let li = [];
    for (let id in menuItems) {
        let obj = menuItems[id];
        const badge = (obj.badge ? <span className={"right badge badge-" + obj.badge.type}>{obj.badge.value}</span> : <></>);

        li.push(
            <li key={id} className={"nav-item"}>
                <NavLink to={"/admin/" + id} className={"nav-link"}>
                    <Icon icon={obj.icon} className={"nav-icon"} /><p>{L(obj.name)}{badge}</p>
                </NavLink>
            </li>
        );
    }

    li.push(<li className={"nav-item"} key={"logout"}>
        <a href={"#"} onClick={() => onLogout()} className={"nav-link"}>
            <Icon icon={"arrow-left"} className={"nav-icon"} />
            <p>{L("general.logout")}</p>
        </a>
    </li>);

    return <>
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
                            <div className="user-panel mt-3 pb-3 mb-3 d-flex">
                                <div className="info">
                                    <span className={"d-block text-light"}>{L("account.logged_in_as")}:&nbsp;
                                        <Link to={"/admin/user/" + api.user.id}>{api.user.name}</Link>
                                    </span>
                                </div>
                            </div>
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
    </>
}

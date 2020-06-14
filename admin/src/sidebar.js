import React from 'react';
import Icon from "./icon";

export default class Sidebar extends React.Component  {

    constructor(props) {
        super(props);
        this.parent = {
            onChangeView: props.onChangeView || function() { },
            showDialog: props.showDialog || function() {},
            api: props.api
        }
        this.state = { currentView: props.currentView, }
    }

    onChangeView(view) {
        this.setState({ ...this.state, currentView: view });
        this.parent.onChangeView(view);
    }

    onLogout() {
        this.parent.api.logout().then(obj => {
           if (obj.success) {
               document.location = "/admin";
           } else {
               this.parent.showDialog({message: "Error logging out: " + obj.msg, title: "Error logging out"});
           }
        });
    }

    render() {

        const menuItems = {
            "dashboard": {
                "name": "Dashboard",
                "icon": "tachometer-alt"
            },
            "users": {
                "name": "Users",
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

        let li = [];
        for (let id in menuItems) {
            let obj = menuItems[id];
            let active = this.state.currentView === id ? " active" : "";
            li.push(<li key={id} className={"nav-item"}>
                <a href={"#"} onClick={() => this.onChangeView(id)} className={"nav-link" + active}>
                    <Icon icon={obj.icon} /><p>{obj.name}</p>
                </a>
            </li>);
        }

        li.push(<li key={"logout"} className={"nav-item"}>
            <a href={"#"} onClick={() => this.onLogout()} className={"nav-link"}>
                <Icon icon={"arrow-left"} classes={"nav-icon"} />
                <p>Logout</p>
            </a>
        </li>);
        
        return <aside className={"main-sidebar sidebar-dark-primary elevation-4"}>
            <a href={"#"} onClick={() => this.onChangeView("dashboard") } className={"brand-link"}>
                <img src={"/img/web_base_logo.png"} alt={"WebBase Logo"} className={"brand-image img-circle elevation-3"} style={{"opacity": ".8"}} />
                <span className={"brand-text font-weight-light"}>WebBase</span>
            </a>

            {/* Sidebar */}
            <div className={"sidebar"}>

                <div className={"mt-2"}>
                    Logged in as: {this.parent.api.user.name}
                </div>

                <hr />

                {/* Sidebar Menu */}
                <nav className={"mt-2"}>
                    <ul className={"nav nav-pills nav-sidebar flex-column"} data-widget={"treeview"} role={"menu"} data-accordion={"false"}>
                        {li}
                    </ul>
                </nav>
            </div>
        </aside>
    }

};
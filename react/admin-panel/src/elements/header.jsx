import * as React from "react";
import {Link} from "react-router-dom";
import Icon from "shared/elements/icon";

export default function Header(props) {

    const parent = {
        api: props.api
    };

    function onToggleSidebar() {
        let classes = document.body.classList;
        if (classes.contains("sidebar-collapse")) {
            classes.remove("sidebar-collapse");
            classes.add("sidebar-open");
        } else {
            classes.add("sidebar-collapse");
            classes.remove("sidebar-add");
        }
    }

    return (
        <nav className={"main-header navbar navbar-expand navbar-white navbar-light"}>

            {/*Left navbar links */}
            <ul className={"navbar-nav"}>
                <li className={"nav-item"}>
                    <a href={"#"} className={"nav-link"} role={"button"} onClick={onToggleSidebar}>
                        <Icon icon={"bars"}/>
                    </a>
                </li>
                <li className={"nav-item d-none d-sm-inline-block"}>
                    <Link to={"/admin/dashboard"} className={"nav-link"}>
                        Home
                    </Link>
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

            </ul>
        </nav>
    )
}

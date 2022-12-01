import React from "react";

export default function Footer(props) {

    return (
        <footer className={"main-footer"}>
            Theme: <strong>Copyright © 2014-2021 <a href={"https://adminlte.io"}>AdminLTE.io</a>. <b>Version</b> 3.2.0</strong>&nbsp;
            Framework: <strong><a href={"https://git.romanh.de/Projekte/web-base"}>WebBase</a></strong>. <b>Version</b> {props.info.version}
        </footer>
    )
}

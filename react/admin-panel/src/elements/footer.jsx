import React from "react";

export default function Footer(props) {

    return (
        <footer className={"main-footer"}>
            Theme: <strong>Copyright © 2014-2019 <a href={"https://adminlte.io"}>AdminLTE.io</a>. <b>Version</b> 3.0.3</strong>&nbsp;
            CMS: <strong><a href={"https://git.romanh.de/Projekte/web-base"}>WebBase</a></strong>. <b>Version</b> {props.info.version}
        </footer>
    )
}

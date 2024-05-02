import React from "react";
import {Divider, styled} from "@mui/material";

const StyledFooter = styled("footer")((props) => ({
    position: "fixed",
    bottom: 0,
    right: 0,
    backgroundColor: "white",
    paddingTop: props.theme.spacing(1),
    paddingRight: props.theme.spacing(1),
    paddingLeft: props.theme.spacing(1),
}));

export default function Footer(props) {

    return <StyledFooter>
        <Divider />
        <b>Framework</b>: <a href={"https://git.romanh.de/Projekte/web-base"} target={"_blank"}>WebBase</a>&nbsp;Version {props.info.version}
    </StyledFooter>
}

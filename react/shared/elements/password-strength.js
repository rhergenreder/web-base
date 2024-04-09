import {Box, styled} from "@mui/material";
import {useContext} from "react";
import {LocaleContext} from "../locale";
import {sprintf} from "sprintf-js";

const PasswordStrengthBox = styled(Box)((props) => ({
    textAlign: "center",
    borderRadius: 5,
    borderStyle: "solid",
    borderWidth: 1,
    borderColor: props.theme.palette.action,
    padding: props.theme.spacing(0.5),
    position: "relative",
    "& > div": {
        zIndex: 0,
        position: "absolute",
        top: 0,
        left: 0,
        height: "100%",
    },
    "& > span": {
        zIndex: 1,
        position: "relative",
    }
}));

export default function PasswordStrength(props) {

    const {password, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    const ref = 14;
    let strength = password.length >= ref ? 100 : Math.round(password.length / ref * 100.0);
    let label = "account.password_very_weak";
    let bgColor = "red";

    if (strength >= 85) {
        label = "account.password_very_strong";
        bgColor = "darkgreen";
    } else if (strength >= 65) {
        label = "account.password_strong";
        bgColor = "green";
    } else if (strength >= 50) {
        label = "account.password_ok";
        bgColor = "yellow";
    } else if (strength >= 25) {
        label = "account.password_weak";
        bgColor = "orange";
    }

    return <PasswordStrengthBox {...other}>
        <Box position={"absolute"} sx={{
            backgroundColor: bgColor,
            width: sprintf("%d%%", strength),
        }} />
        <span>{L(label)}</span>
    </PasswordStrengthBox>
}
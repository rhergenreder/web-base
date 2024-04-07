import {Box, Paper} from "@mui/material";
import {LocaleContext} from "shared/locale";
import {useCallback, useContext} from "react";

export default function MfaFido(props) {

    const {api, showDialog, setDialogData, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    const openDialog = useCallback(() => {
        if (api.hasPermission("tfa/registerKey")) {

        }
    }, [api, showDialog]);

    const disabledStyle = {
        background: "gray",
        cursor: "not-allowed"
    }

    return <Box component={Paper} onClick={openDialog}
                style={!api.hasPermission("tfa/registerKey") ? disabledStyle : {}}>
        <div><img src={"/img/icons/nitrokey.png"} alt={"[Nitro Key]"} /></div>
        <div>{L("account.2fa_type_fido")}</div>
    </Box>;
}
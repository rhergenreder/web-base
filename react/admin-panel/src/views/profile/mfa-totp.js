import {Box, Paper} from "@mui/material";
import {useCallback, useContext} from "react";
import {LocaleContext} from "shared/locale";

export default function MfaTotp(props) {

    const {setDialogData, api, showDialog, set2FA, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    const onConfirmTOTP = useCallback((code) => {
        api.confirmTOTP(code).then(data => {
            if (!data.success) {
                showDialog(data.msg, L("account.confirm_totp_error"));
            } else {
                setDialogData({show: false});
                showDialog(L("account.confirm_totp_success"), L("general.success"));
                set2FA({ confirmed: true, type: "totp", authenticated: true });
            }
        });
        return false;
    }, [api, showDialog, set2FA, setDialogData]);

    const openDialog = useCallback(() => {
        if (api.hasPermission("tfa/generateQR")) {
            setDialogData({
                show: true,
                title: L("account.register_2fa_device"),
                message: L("account.register_2fa_totp_text"),
                inputs: [
                    {
                        type: "custom", element: Box, textAlign: "center", key: "qr-code",
                        children: <img src={"/api/tfa/generateQR?nocache=" + Math.random()} alt={"[QR-Code]"} />
                    },
                    {
                        type: "number", placeholder: L("account.6_digit_code"),
                        inputProps: { maxLength: 6 }, name: "code",
                        sx: { "& input": { textAlign: "center", fontFamily: "monospace" } },
                    }
                ],
                options: [L("general.ok"), L("general.cancel")],
                onOption: (option, data) => option === 0 ? onConfirmTOTP(data.code) : true
            })
        }
    }, [api, onConfirmTOTP]);

    const disabledStyle = {
        background: "gray",
        cursor: "not-allowed"
    }

    return <Box component={Paper} onClick={openDialog}
                style={!api.hasPermission("tfa/generateQR") ? disabledStyle : {}}>
        <div><img src={"/img/icons/google_authenticator.svg"} alt={"[Google Authenticator]"} /></div>
        <div>{L("account.2fa_type_totp")}</div>
    </Box>
}
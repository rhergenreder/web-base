import {Box, CircularProgress, Paper} from "@mui/material";
import {LocaleContext} from "shared/locale";
import {useCallback, useContext} from "react";
import {decodeText, encodeText} from "shared/util";

export default function MfaFido(props) {

    const {api, showDialog, setDialogData, set2FA, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    const openDialog = useCallback(() => {
        if (!api.hasPermission("tfa/registerKey")) {
            return;
        }

        if (typeof navigator.credentials !== 'object' || typeof navigator.credentials.create !== 'function') {
            showDialog(L("Key-based Two-Factor-Authentication (2FA) is not supported on this device."), L("Not supported"));
        }

        api.register2FA().then(res => {
            if (!res.success) {
                showDialog(res.msg, L("Error registering 2FA-Device"));
                return;
            }

            setDialogData({
                show: true,
                title: L("Register a 2FA-Device"),
                message: L("You may need to interact with your Device, e.g. typing in your PIN or touching to confirm the registration."),
                inputs: [
                    { type: "custom", key: "progress", element: CircularProgress }
                ],
                options: [L("general.cancel")],
            })

            navigator.credentials.create({
                publicKey: {
                    challenge: encodeText(window.atob(res.data.challenge)),
                    rp: res.data.relyingParty,
                    user: {
                        id: encodeText(api.user.id),
                        name: api.user.name,
                        displayName: api.user.fullName
                    },
                    authenticatorSelection: {
                        authenticatorAttachment: "cross-platform",
                        requireResidentKey: false,
                        userVerification: "discouraged"
                    },
                    attestation: "direct",
                    pubKeyCredParams: [{
                        type: "public-key",
                        alg: -7, // "ES256" IANA COSE Algorithms registry
                    }]
                }
            }).then(res => {
                if (res.response) {
                    let clientDataJSON = decodeText(res.response.clientDataJSON);
                    let attestationObject = window.btoa(String.fromCharCode.apply(null, new Uint8Array(res.response.attestationObject)));
                    api.register2FA(clientDataJSON, attestationObject).then((res) => {
                        setDialogData({show: false});
                        if (res.success) {
                            showDialog(L("account.confirm_fido_success"), L("general.success"));
                            set2FA({ confirmed: true, type: "fido", authenticated: true });
                        } else {
                            showDialog(res.msg, L("Error registering 2FA-Device"));
                        }
                    });
                } else {
                    showDialog(JSON.stringify(res), L("Error registering 2FA-Device"));
                }
            }).catch(ex => {
                setDialogData({show: false});
                showDialog(ex.toString(), L("Error registering 2FA-Device"));
            });
        });
    }, [api, showDialog, setDialogData, set2FA]);

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
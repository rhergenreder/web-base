import React, {useCallback, useContext, useState} from "react";
import {LocaleContext} from "shared/locale";
import {CheckCircle, ErrorOutline, Fingerprint, Remove} from "@mui/icons-material";
import {Box, Button, CircularProgress, FormControl, FormLabel, styled, TextField} from "@mui/material";
import SpacedFormGroup from "../../elements/form-group";
import MfaTotp from "./mfa-totp";
import MfaFido from "./mfa-fido";
import CollapseBox from "./collapse-box";

const MfaStatusBox = styled(Box)((props) => ({
    display: "grid",
    gridTemplateColumns: "30px auto",
}));

const MFAOptions = styled(Box)((props) => ({
    "& > div": {
        borderColor: props.theme.palette.divider,
        borderStyle: "solid",
        borderWidth: 1,
        borderRadius: 5,
        maxWidth: 150,
        cursor: "pointer",
        textAlign: "center",
        display: "inline-grid",
        gridTemplateRows: "130px 50px",
        alignItems: "center",
        padding: props.theme.spacing(1),
        marginRight: props.theme.spacing(1),
        "&:hover": {
            backgroundColor: "lightgray",
        },
        "& img": {
            width: "100%",
        },
    }
}));

export default function MultiFactorBox(props) {

    // meta
    const {profile, setProfile, setDialogData, api, showDialog, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    // data
    const [mfaPassword, set2FAPassword] = useState("");

    // ui
    const [is2FARemoving, set2FARemoving] = useState(false);

    const onRemove2FA = useCallback(() => {
        if (!is2FARemoving) {
            set2FARemoving(true);
            api.remove2FA(mfaPassword).then(data => {
                set2FARemoving(false);
                set2FAPassword("");
                if (!data.success) {
                    showDialog(data.msg, L("account.remove_2fa_error"));
                } else {
                    setProfile({...profile, twoFactorToken: null});
                }
            });
        }
    }, [api, showDialog, is2FARemoving, mfaPassword, profile]);

    return <CollapseBox title={L("account.2fa_token")}
                        icon={<Fingerprint />} {...other}>
        {profile.twoFactorToken && profile.twoFactorToken.confirmed ?
            <Box>
                <MfaStatusBox mb={2}>
                    <CheckCircle color="info" title={L("account.two_factor_confirmed")} />
                    <span>{L("account.2fa_type_" + profile.twoFactorToken.type)}</span>
                </MfaStatusBox>
                <SpacedFormGroup>
                    <FormLabel>{L("account.password")}</FormLabel>
                    <FormControl>
                        <TextField variant={"outlined"} size="small"
                                   value={mfaPassword} type={"password"}
                                   onChange={e => set2FAPassword(e.target.value)}
                                   placeholder={L("account.password")}
                        />
                    </FormControl>
                </SpacedFormGroup>
                <Button startIcon={is2FARemoving ? <CircularProgress size={12} /> : <Remove />}
                        color="error" onClick={onRemove2FA}
                        variant="outlined" size="small"
                        disabled={is2FARemoving || !api.hasPermission("tfa/remove")}>
                    {is2FARemoving ? L("general.removing") + "…" : L("general.remove")}
                </Button>
            </Box> :
            <MFAOptions>
                <MfaTotp api={api} showDialog={showDialog} setDialogData={setDialogData}
                         set2FA={token => setProfile({...profile, twoFactorToken: token })} />
                <MfaFido api={api} showDialog={showDialog} setDialogData={setDialogData}
                         set2FA={token => setProfile({...profile, twoFactorToken: token })} />
            </MFAOptions>
        }
    </CollapseBox>
}
import React, {useCallback, useContext, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Box, Button, CircularProgress, FormControl, FormLabel, styled, TextField} from "@mui/material";
import {CheckCircle, CloudUpload, ErrorOutline, Remove, Upload, VpnKey} from "@mui/icons-material";
import SpacedFormGroup from "../../elements/form-group";
import ButtonBar from "../../elements/button-bar";
import CollapseBox from "./collapse-box";

const GpgKeyField = styled(TextField)((props) => ({
    "& > div": {
        fontFamily: "monospace",
        padding: props.theme.spacing(1),
        fontSize: '0.8rem',
    },
    marginBottom: props.theme.spacing(1)
}));

const GpgFingerprintBox = styled(Box)((props) => ({
    "& > svg": {
        marginRight: props.theme.spacing(1),
    },
    "& > code": {
        cursor: "pointer"
    }
}));

const VisuallyHiddenInput = styled('input')({
    clip: 'rect(0 0 0 0)',
    clipPath: 'inset(50%)',
    height: 1,
    overflow: 'hidden',
    position: 'absolute',
    bottom: 0,
    left: 0,
    whiteSpace: 'nowrap',
    width: 1,
});

export default function GpgBox(props) {

    // meta
    const {profile, setProfile, api, showDialog, ...other} = props;
    const {translate: L} = useContext(LocaleContext);

    // data
    const [gpgKey, setGpgKey] = useState("");
    const [gpgKeyPassword, setGpgKeyPassword] = useState("");

    // ui
    const [isGpgKeyUploading, setGpgKeyUploading] = useState(false);
    const [isGpgKeyRemoving, setGpgKeyRemoving] = useState(false);

    const onUploadGPG = useCallback(() => {
        if (!isGpgKeyUploading) {
            setGpgKeyUploading(true);
            api.uploadGPG(gpgKey).then(data => {
                setGpgKeyUploading(false);
                if (!data.success) {
                    showDialog(data.msg, L("account.upload_gpg_error"));
                } else {
                    setProfile({...profile, gpgKey: data.gpgKey});
                    setGpgKey("");
                }
            });
        }
    }, [api, showDialog, isGpgKeyUploading, profile, gpgKey]);

    const onRemoveGpgKey = useCallback(() => {
        if (!isGpgKeyRemoving) {
            setGpgKeyRemoving(true);
            api.removeGPG(gpgKeyPassword).then(data => {
                setGpgKeyRemoving(false);
                setGpgKeyPassword("");
                if (!data.success) {
                    showDialog(data.msg, L("account.remove_gpg_error"));
                } else {
                    setProfile({...profile, gpgKey: null});
                }
            });
        }
    }, [api, showDialog, isGpgKeyRemoving, gpgKeyPassword, profile]);

    const getFileContents = useCallback((file, callback) => {
        let reader = new FileReader();
        let data = "";
        reader.onload = function(event) {
            data += event.target.result;
            if (reader.readyState === 2) {
                if (!data.match(/^-+\s*BEGIN/m)) {
                    showDialog(L("Selected file is a not a GPG Public Key in ASCII format"), L("Error reading file"));
                    return false;
                } else {
                    callback(data);
                }
            }
        };
        setGpgKey("");
        reader.readAsText(file);
    }, [showDialog]);

    return <CollapseBox title={L("account.gpg_key")} {...other}

                        icon={<VpnKey />}>
        {
            profile.gpgKey ? <Box>
                    <GpgFingerprintBox mb={2}>
                        { profile.gpgKey.confirmed ?
                            <CheckCircle color="info" title={L("account.gpg_key_confirmed")} /> :
                            <ErrorOutline color="error" title={L("account.gpg_key_pending")}  />
                        }
                        GPG-Fingerprint: <code title={L("general.click_to_copy")}
                                               onClick={() => navigator.clipboard.writeText(profile.gpgKey.fingerprint)}>
                            {profile.gpgKey.fingerprint}
                        </code>
                    </GpgFingerprintBox>
                    <SpacedFormGroup>
                        <FormLabel>{L("account.password")}</FormLabel>
                        <FormControl>
                            <TextField variant={"outlined"} size="small"
                                       value={gpgKeyPassword} type={"password"}
                                       onChange={e => setGpgKeyPassword(e.target.value)}
                                       placeholder={L("account.password")}
                            />
                        </FormControl>
                    </SpacedFormGroup>
                    <Button startIcon={isGpgKeyRemoving ? <CircularProgress size={12} /> : <Remove />}
                            color="error" onClick={onRemoveGpgKey}
                            variant="outlined" size="small"
                            disabled={isGpgKeyRemoving || !api.hasPermission("gpgKey/remove")}>
                        {isGpgKeyRemoving ? L("general.removing") + "…" : L("general.remove")}
                    </Button>
                </Box> :
                <Box>
                    <SpacedFormGroup>
                        <FormLabel>{L("account.gpg_key")}</FormLabel>
                        <GpgKeyField value={gpgKey} multiline={true} rows={8}
                                     disabled={isGpgKeyUploading || !api.hasPermission("gpgKey/import")}
                                     placeholder={L("account.gpg_key_placeholder_text")}
                                     onChange={e => setGpgKey(e.target.value)}
                                     onDrop={e => {
                                         let file = e.dataTransfer.files[0];
                                         getFileContents(file, (data) => {
                                             setGpgKey(data);
                                         });
                                         return false;
                                     }}/>
                    </SpacedFormGroup>
                    <ButtonBar>
                        <Button size="small"
                                variant="outlined"
                                startIcon={<CloudUpload />}
                                component={"label"}>
                            Upload file
                            <VisuallyHiddenInput type={"file"}  onChange={e => {
                                let file = e.target.files[0];
                                getFileContents(file, (data) => {
                                    setGpgKey(data);
                                });
                                return false;
                            }} />
                        </Button>
                        <Button startIcon={isGpgKeyUploading ? <CircularProgress size={12} /> : <Upload />}
                                color="primary" onClick={onUploadGPG}
                                variant="outlined" size="small"
                                disabled={isGpgKeyUploading || !api.hasPermission("gpgKey/import")}>
                            {isGpgKeyUploading ? L("general.uploading") + "…" : L("general.upload")}
                        </Button>
                    </ButtonBar>
                </Box>
        }
    </CollapseBox>
}
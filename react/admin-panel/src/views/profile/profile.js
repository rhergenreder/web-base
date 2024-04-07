import {Link} from "react-router-dom";
import React, {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    Box,
    Button,
    CircularProgress,
    FormControl,
    FormGroup,
    FormLabel, Paper, styled,
    TextField
} from "@mui/material";
import {
    CheckCircle,
    CloudUpload,
    ErrorOutline,
    Fingerprint,
    Password,
    Remove,
    Save,
    Upload,
    VpnKey
} from "@mui/icons-material";
import CollapseBox from "./collapse-box";
import ButtonBar from "../../elements/button-bar";
import MfaTotp from "./mfa-totp";
import MfaFido from "./mfa-fido";
import Dialog from "shared/elements/dialog";

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

const ProfileFormGroup = styled(FormGroup)((props) =>  ({
    marginBottom: props.theme.spacing(2)
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

export default function ProfileView(props) {

    // meta
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const api = props.api;
    const showDialog = props.showDialog;

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog(data.msg, "Error fetching localization");
            }
        });
    }, [currentLocale]);

    // data
    const [profile, setProfile] = useState({...api.user});
    const [changePassword, setChangePassword] = useState({ old: "", new: "", confirm: "" });
    const [gpgKey, setGpgKey] = useState("");
    const [gpgKeyPassword, setGpgKeyPassword] = useState("");
    const [mfaPassword, set2FAPassword] = useState("");

    // ui
    const [openedTab, setOpenedTab] = useState(null);
    const [isSaving, setSaving] = useState(false);
    const [isGpgKeyUploading, setGpgKeyUploading] = useState(false);
    const [isGpgKeyRemoving, setGpgKeyRemoving] = useState(false);
    const [is2FARemoving, set2FARemoving] = useState(false);
    const [dialogData, setDialogData] = useState({show: false});

    const onUpdateProfile = useCallback(() => {

        if (!isSaving) {

            let newUsername = (profile.name !== api.user.name ? profile.name : null);
            let newFullName = (profile.fullName !== api.user.fullName ? profile.fullName : null);

            let oldPassword = null;
            let newPassword = null;
            let confirmPassword = null;
            if (changePassword.new || changePassword.confirm) {
                if (changePassword.new !== changePassword.confirm) {
                    showDialog(L("account.passwords_do_not_match"), L("general.error"));
                    return;
                } else {
                    oldPassword = changePassword.old;
                    newPassword = changePassword.new;
                    confirmPassword = changePassword.confirm;
                }
            }

            setSaving(true);
            api.updateProfile(newUsername, newFullName, newPassword, confirmPassword, oldPassword).then(data => {
                setSaving(false);
                if (!data.success) {
                    showDialog(data.msg, L("account.update_profile_error"));
                } else {
                    setChangePassword({old: "", new: "", confirm: ""});
                }
            });
        }

    }, [profile, changePassword, api, showDialog, isSaving]);

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

    console.log("SELECTED USER:", profile.twoFactorToken);

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>
                            {L("account.edit_profile")}
                        </h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">{L("account.profile")}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div className={"content"}>
            <ProfileFormGroup>
                <FormLabel>{L("account.username")}</FormLabel>
                <FormControl>
                    <TextField variant={"outlined"}
                        size={"small"}
                        value={profile.name}
                        onChange={e => setProfile({...profile, name: e.target.value })} />
                </FormControl>
            </ProfileFormGroup>
            <ProfileFormGroup>
                <FormLabel>{L("account.full_name")}</FormLabel>
                <FormControl>
                    <TextField variant={"outlined"}
                               size={"small"}
                               value={profile.fullName ?? ""}
                               onChange={e => setProfile({...profile, fullName: e.target.value })} />
                </FormControl>
            </ProfileFormGroup>

            <CollapseBox title={L("account.change_password")} open={openedTab === "password"}
                         onToggle={() => setOpenedTab(openedTab === "password" ? "" : "password")}
                         icon={<Password />}>
                <ProfileFormGroup>
                    <FormLabel>{L("account.password_old")}</FormLabel>
                    <FormControl>
                        <TextField variant={"outlined"}
                                   size={"small"}
                                   type={"password"}
                                   placeholder={L("general.unchanged")}
                                   value={changePassword.old}
                                   onChange={e => setChangePassword({...changePassword, old: e.target.value })} />
                    </FormControl>
                </ProfileFormGroup>
                <ProfileFormGroup>
                    <FormLabel>{L("account.password_new")}</FormLabel>
                    <FormControl>
                        <TextField variant={"outlined"}
                                   size={"small"}
                                   type={"password"}
                                   value={changePassword.new}
                                   onChange={e => setChangePassword({...changePassword, new: e.target.value })} />
                    </FormControl>
                </ProfileFormGroup>
                <ProfileFormGroup>
                    <FormLabel>{L("account.password_confirm")}</FormLabel>
                    <FormControl>
                        <TextField variant={"outlined"}
                                   size={"small"}
                                   type={"password"}
                                   value={changePassword.confirm}
                                   onChange={e => setChangePassword({...changePassword, confirm: e.target.value })} />
                    </FormControl>
                </ProfileFormGroup>
            </CollapseBox>

            <CollapseBox title={L("account.gpg_key")} open={openedTab === "gpg"}
                         onToggle={() => setOpenedTab(openedTab === "gpg" ? "" : "gpg")}
                         icon={<VpnKey />}>
                {
                    profile.gpgKey ? <Box>
                            <GpgFingerprintBox mb={2}>
                                { profile.gpgKey.confirmed ?
                                    <CheckCircle color={"info"} title={L("account.gpg_key_confirmed")} /> :
                                    <ErrorOutline color={"secondary"} title={L("account.gpg_key_pending")}  />
                                }
                                GPG-Fingerprint: <code title={L("general.click_to_copy")} onClick={() => navigator.clipboard.writeText(profile.gpgKey.fingerprint)}>
                                    {profile.gpgKey.fingerprint}
                                </code>
                            </GpgFingerprintBox>
                            <ProfileFormGroup>
                                <FormLabel>{L("account.password")}</FormLabel>
                                <FormControl>
                                    <TextField variant={"outlined"} size={"small"}
                                               value={gpgKeyPassword} type={"password"}
                                               onChange={e => setGpgKeyPassword(e.target.value)}
                                               placeholder={L("account.password")}
                                    />
                                </FormControl>
                            </ProfileFormGroup>
                            <Button startIcon={isGpgKeyRemoving ? <CircularProgress size={12} /> : <Remove />}
                                    color={"secondary"} onClick={onRemoveGpgKey}
                                    variant={"outlined"} size={"small"}
                                    disabled={isGpgKeyRemoving || !api.hasPermission("user/removeGPG")}>
                                {isGpgKeyRemoving ? L("general.removing") + "…" : L("general.remove")}
                            </Button>
                    </Box> :
                    <Box>
                        <ProfileFormGroup>
                            <FormLabel>{L("account.gpg_key")}</FormLabel>
                            <GpgKeyField value={gpgKey} multiline={true} rows={8}
                                         disabled={isGpgKeyUploading || !api.hasPermission("user/importGPG")}
                                         placeholder={L("account.gpg_key_placeholder_text")}
                                         onChange={e => setGpgKey(e.target.value)}
                                         onDrop={e => {
                                             let file = e.dataTransfer.files[0];
                                             getFileContents(file, (data) => {
                                                 setGpgKey(data);
                                             });
                                             return false;
                                         }}/>
                        </ProfileFormGroup>
                        <ButtonBar>
                            <Button size={"small"}
                                variant={"outlined"}
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
                                    color={"primary"} onClick={onUploadGPG}
                                    variant={"outlined"} size={"small"}
                                    disabled={isGpgKeyUploading || !api.hasPermission("user/importGPG")}>
                                {isGpgKeyUploading ? L("general.uploading") + "…" : L("general.upload")}
                            </Button>
                        </ButtonBar>
                    </Box>
                }
            </CollapseBox>

            <CollapseBox title={L("account.2fa_token")} open={openedTab === "2fa"}
                         onToggle={() => setOpenedTab(openedTab === "2fa" ? "" : "2fa")}
                         icon={<Fingerprint />}>
                {profile.twoFactorToken && profile.twoFactorToken.confirmed ?
                    <Box>
                        <GpgFingerprintBox mb={2}>
                            { profile.twoFactorToken.confirmed ?
                                <CheckCircle color={"info"} title={L("account.gpg_key_confirmed")} /> :
                                <ErrorOutline color={"secondary"} title={L("account.gpg_key_pending")}  />
                            }
                            {L("account.2fa_type_" + profile.twoFactorToken.type)}
                        </GpgFingerprintBox>
                        <ProfileFormGroup>
                            <FormLabel>{L("account.password")}</FormLabel>
                            <FormControl>
                                <TextField variant={"outlined"} size={"small"}
                                           value={mfaPassword} type={"password"}
                                           onChange={e => set2FAPassword(e.target.value)}
                                           placeholder={L("account.password")}
                                />
                            </FormControl>
                        </ProfileFormGroup>
                        <Button startIcon={is2FARemoving ? <CircularProgress size={12} /> : <Remove />}
                                color={"secondary"} onClick={onRemove2FA}
                                variant={"outlined"} size={"small"}
                                disabled={is2FARemoving || !api.hasPermission("tfa/remove")}>
                            {is2FARemoving ? L("general.removing") + "…" : L("general.remove")}
                        </Button>
                    </Box> :
                    <MFAOptions>
                        <MfaTotp api={api} showDialog={showDialog} setDialogData={setDialogData}/>
                        <MfaFido api={api} showDialog={showDialog} setDialogData={setDialogData}/>
                    </MFAOptions>
                }
            </CollapseBox>

            <Box mt={2}>
                <Button variant={"outlined"} color={"primary"}
                    disabled={isSaving || !api.hasPermission("user/updateProfile")}
                    startIcon={isSaving ? <CircularProgress size={12} /> : <Save />}
                    onClick={onUpdateProfile}>
                        {isSaving ? L("general.saving") + "…" : L("general.save")}
                </Button>
            </Box>
        </div>

        <Dialog show={dialogData.show}
                title={dialogData.title}
                message={dialogData.message}
                inputs={dialogData.inputs}
                onClose={() => setDialogData({show: false})}
                options={[L("general.ok"), L("general.cancel")]}
                onOption={dialogData.onOption} />
    </>
}
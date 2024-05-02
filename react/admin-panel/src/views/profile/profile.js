import {Link} from "react-router-dom";
import React, {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    Box,
    Button,
    CircularProgress,
    FormControl,
    FormLabel,
    TextField
} from "@mui/material";
import {
    Save,
} from "@mui/icons-material";
import Dialog from "shared/elements/dialog";
import SpacedFormGroup from "../../elements/form-group";
import ChangePasswordBox from "./change-password-box";
import GpgBox from "./gpg-box";
import MultiFactorBox from "./mfa-box";
import EditProfilePicture from "./edit-picture";
import ViewContent from "../../elements/view-content";

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

    // ui
    const [openedTab, setOpenedTab] = useState(null);
    const [isSaving, setSaving] = useState(false);
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

    return <>
        <ViewContent title={L("account.edit_profile")} path={[
            <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
            <span key={"profile"}>{L("account.profile")}</span>
        ]}>
            <Box display={"grid"} gridTemplateColumns={"300px auto"}>
                <EditProfilePicture api={api} showDialog={showDialog} setProfile={setProfile}
                                    profile={profile} setDialogData={setDialogData} />
                <Box p={2}>
                    <SpacedFormGroup>
                        <FormLabel>{L("account.username")}</FormLabel>
                        <FormControl>
                            <TextField variant={"outlined"}
                                       size={"small"}
                                       value={profile.name}
                                       onChange={e => setProfile({...profile, name: e.target.value })} />
                        </FormControl>
                    </SpacedFormGroup>
                    <SpacedFormGroup>
                        <FormLabel>{L("account.full_name")}</FormLabel>
                        <FormControl>
                            <TextField variant={"outlined"}
                                       size={"small"}
                                       value={profile.fullName ?? ""}
                                       onChange={e => setProfile({...profile, fullName: e.target.value })} />
                        </FormControl>
                    </SpacedFormGroup>
                    <SpacedFormGroup>
                        <FormLabel>{L("account.email")}</FormLabel>
                        <FormControl>
                            <TextField variant={"outlined"}
                                       size={"small"}
                                       value={profile.email ?? ""}
                                       disabled={true}/>
                        </FormControl>
                    </SpacedFormGroup>
                </Box>
            </Box>

            <ChangePasswordBox open={openedTab === "password"}
                               onToggle={() => setOpenedTab(openedTab === "password" ? "" : "password")}
                               changePassword={changePassword}
                               setChangePassword={setChangePassword} />

            <GpgBox open={openedTab === "gpg"}
                    onToggle={() => setOpenedTab(openedTab === "gpg" ? "" : "gpg")}
                    profile={profile} setProfile={setProfile}
                    api={api} showDialog={showDialog} />

            <MultiFactorBox open={openedTab === "2fa"}
                            onToggle={() => setOpenedTab(openedTab === "2fa" ? "" : "2fa")}
                            profile={profile} setProfile={setProfile}
                            setDialogData={setDialogData}
                            api={api} showDialog={showDialog} />

            <Box mt={2}>
                <Button variant={"outlined"} color={"primary"}
                        disabled={isSaving || !api.hasPermission("user/updateProfile")}
                        startIcon={isSaving ? <CircularProgress size={12} /> : <Save />}
                        onClick={onUpdateProfile}>
                    {isSaving ? L("general.saving") + "…" : L("general.save")}
                </Button>
            </Box>
        </ViewContent>
        <Dialog show={dialogData.show}
                title={dialogData.title}
                message={dialogData.message}
                inputs={dialogData.inputs}
                onClose={() => setDialogData({show: false})}
                options={dialogData.options}
                onOption={dialogData.onOption} />
    </>
}
import {Link} from "react-router-dom";
import React, {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Button, CircularProgress, FormControl, FormGroup, FormLabel, TextField} from "@mui/material";
import {Save} from "@mui/icons-material";

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
    const [isSaving, setSaving] = useState(false);

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
            <FormGroup>
                <FormLabel>{L("account.username")}</FormLabel>
                <FormControl>
                    <TextField variant={"outlined"}
                        size={"small"}
                        value={profile.name}
                        onChange={e => setProfile({...profile, name: e.target.value })} />
                </FormControl>
            </FormGroup>
            <FormGroup>
                <FormLabel>{L("account.full_name")}</FormLabel>
                <FormControl>
                    <TextField variant={"outlined"}
                               size={"small"}
                               value={profile.fullName ?? ""}
                               onChange={e => setProfile({...profile, fullName: e.target.value })} />
                </FormControl>
            </FormGroup>
            <h4>{L("account.change_password")}</h4>
            <FormGroup>
                <FormLabel>{L("account.old_password")}</FormLabel>
                <FormControl>
                    <TextField variant={"outlined"}
                               size={"small"}
                               type={"password"}
                               placeholder={L("general.unchanged")}
                               value={changePassword.old}
                               onChange={e => setChangePassword({...changePassword, old: e.target.value })} />
                </FormControl>
            </FormGroup>
            <FormGroup>
                <FormLabel>{L("account.new_password")}</FormLabel>
                <FormControl>
                    <TextField variant={"outlined"}
                               size={"small"}
                               type={"password"}
                               value={changePassword.new}
                               onChange={e => setChangePassword({...changePassword, new: e.target.value })} />
                </FormControl>
            </FormGroup>
            <FormGroup>
                <FormLabel>{L("account.confirm_password")}</FormLabel>
                <FormControl>
                    <TextField variant={"outlined"}
                               size={"small"}
                               type={"password"}
                               placeholder={L("general.unchanged")}
                               value={changePassword.confirm}
                               onChange={e => setChangePassword({...changePassword, confirm: e.target.value })} />
                </FormControl>
            </FormGroup>
            <Button variant={"outlined"} color={"primary"}
                disabled={isSaving || !api.hasPermission("user/updateProfile")}
                startIcon={isSaving ? <CircularProgress size={12} /> : <Save />}
                onClick={onUpdateProfile}>
                    {isSaving ? L("general.saving") + "…" : L("general.save")}
            </Button>
        </div>
    </>
}
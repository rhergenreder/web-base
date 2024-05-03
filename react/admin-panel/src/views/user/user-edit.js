import {Link, useNavigate, useParams} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {
    Box,
    Button,
    Checkbox,
    CircularProgress,
    FormControl,
    FormControlLabel,
    FormLabel, Grid,
    TextField,
    FormGroup as MuiFormGroup
} from "@mui/material";
import {LocaleContext} from "shared/locale";
import * as React from "react";
import ViewContent from "../../elements/view-content";
import FormGroup from "../../elements/form-group";
import ButtonBar from "../../elements/button-bar";
import {RestartAlt, Save, Send} from "@mui/icons-material";
import PasswordStrength from "shared/elements/password-strength";

const initialUser = {
    name: "",
    fullName: "",
    email: "",
    password: "",
    passwordConfirm: "",
    groups: [],
    confirmed: false,
    active: true,
};

export default function UserEditView(props) {

    // meta
    const { api, showDialog } = props;
    const { userId } = useParams();
    const navigate = useNavigate();

    // data
    const isNewUser = userId === "new";
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const [fetchUser, setFetchUser] = useState(!isNewUser);
    const [user, setUser] = useState(isNewUser ? initialUser : null);

    // ui
    const [hasChanged, setChanged] = useState(isNewUser);
    const [isSaving, setSaving] = useState(false);
    const [sendInvite, setSetInvite] = useState(isNewUser);

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchUser = useCallback((force = false) => {
        if (!isNewUser && (force || fetchUser)) {
            setFetchUser(false);
            api.getUser(userId).then((res) => {
                if (!res.success) {
                    showDialog(res.msg, L("account.error_user_get"));
                    if (user === null) {
                        navigate("/admin/users");
                    }
                } else {
                    setUser(res.user);
                }
            });
        }
    }, [api, showDialog, fetchUser, isNewUser, userId, user]);

    const onReset = useCallback(() => {
        if (isNewUser) {
            setUser({...initialUser});
        } else {
            onFetchUser(true);
        }
    }, [isNewUser, onFetchUser]);

    const onSaveUser = useCallback(() => {
        if (!isSaving) {
            setSaving(true);
            if (isNewUser) {
                if (sendInvite) {
                    api.inviteUser(user.name, user.fullName, user.email).then(res => {
                        setSaving(false);
                        if (res.success) {
                            setChanged(false);
                            navigate("/admin/user/" + res.userId);
                        } else {
                            showDialog(res.msg, L("account.invite_user_error"));
                        }
                    });
                } else {
                    api.createUser(user.name, user.fullName, user.email, user.password, user.passwordConfirm).then(res => {
                        setSaving(false);
                        if (res.success) {
                            setChanged(false);
                            navigate("/admin/user/" + res.userId);
                        } else {
                            showDialog(res.msg, L("account.create_user_error"));
                        }
                    });
                }
            } else {
                api.editUser(
                    userId, user.name, user.email, user.password,
                    user.groups, user.confirmed, user.active
                ).then(res => {
                    setSaving(false);
                    if (res.success) {
                        setChanged(false);
                    } else {
                        showDialog(res.msg, L("account.save_user_error"));
                    }
                });
            }
        }

    }, [isSaving, sendInvite, isNewUser, userId, showDialog]);

    const onChangeValue = useCallback((name, value) => {
        setUser({...user, [name]: value});
        setChanged(true);
    }, [user]);

    useEffect(() => {
        if (!isNewUser) {
            onFetchUser(true);
        }
    }, []);

    if (user === null) {
        return <CircularProgress />
    }

    return <ViewContent title={L(isNewUser ? "account.new_user" : "account.edit_user")} path={[
        <Link key={"dashboard"} to={"/admin/dashboard"}>Home</Link>,
        <Link key={"users"} to={"/admin/users"}>User</Link>,
        <span key={"action"}>{isNewUser ? "New" : "Edit"}</span>
    ]}>
        <Grid container>
            <Grid item xs={12} lg={6}>
            <FormGroup>
                <FormLabel>{L("account.name")}</FormLabel>
                <FormControl>
                    <TextField size={"small"} variant={"outlined"}
                               value={user.name}
                               onChange={e => setUser({...user, name: e.target.value})} />
                </FormControl>
            </FormGroup>
            <FormGroup>
                <FormLabel>{L("account.full_name")}</FormLabel>
                <FormControl>
                    <TextField size={"small"} variant={"outlined"}
                               value={user.fullName}
                               onChange={e => setUser({...user, fullName: e.target.value})} />
                </FormControl>
            </FormGroup>
            <FormGroup>
                <FormLabel>{L("account.email")}</FormLabel>
                <FormControl>
                    <TextField size={"small"} variant={"outlined"}
                               value={user.email}
                               type={"email"}
                               onChange={e => setUser({...user, email: e.target.value})} />
                </FormControl>
            </FormGroup>
            { !isNewUser ?
                <>
                    <FormGroup>
                        <FormLabel>{L("account.password")}</FormLabel>
                        <FormControl>
                            <TextField size={"small"} variant={"outlined"}
                                       value={user.password}
                                       type={"password"}
                                       placeholder={"(" + L("general.unchanged") + ")"}
                                       onChange={e => setUser({...user, password: e.target.value})} />
                        </FormControl>
                    </FormGroup>
                    <MuiFormGroup>
                        <FormControlLabel
                            control={<Checkbox
                                checked={!!user.active}
                                onChange={(e, v) => onChangeValue("active", v)} />}
                            label={L("account.active")} />
                    </MuiFormGroup>
                    <FormGroup>
                        <FormControlLabel
                            control={<Checkbox
                                checked={!!user.confirmed}
                                onChange={(e, v) => onChangeValue("confirmed", v)} />}
                            label={L("account.confirmed")} />
                    </FormGroup>
                </> : <>
                    <FormGroup>
                        <FormControlLabel
                            control={<Checkbox
                                checked={sendInvite}
                                onChange={(e, v) => setSetInvite(v)} />}
                            label={L("account.send_invite")} />
                    </FormGroup>
                    {!sendInvite && <>
                        <FormGroup>
                            <FormLabel>{L("account.password")}</FormLabel>
                            <FormControl>
                                <TextField size={"small"} variant={"outlined"}
                                           value={user.password}
                                           type={"password"}
                                           onChange={e => setUser({...user, password: e.target.value})} />
                            </FormControl>
                        </FormGroup>
                        <FormGroup>
                            <FormLabel>{L("account.password_confirm")}</FormLabel>
                            <FormControl>
                                <TextField size={"small"} variant={"outlined"}
                                           value={user.passwordConfirm}
                                           type={"password"}
                                           onChange={e => setUser({...user, passwordConfirm: e.target.value})} />
                            </FormControl>
                        </FormGroup>
                        <Box mb={2}>
                            <PasswordStrength password={user.password} />
                        </Box>
                    </>
                    }
                </>
            }
            </Grid>
        </Grid>
        <ButtonBar>
            <Button color={"primary"}
                    onClick={onSaveUser}
                    disabled={isSaving || !(isNewUser ? api.hasPermission("user/create") : api.hasPermission("user/edit"))}
                    startIcon={isSaving ?
                        <CircularProgress size={14} /> :
                        (sendInvite ? <Send /> : <Save /> )}
                    variant={"outlined"} title={L(hasChanged ? "general.unsaved_changes" : "general.save")}>
                {isSaving ?
                    L(sendInvite ? "general.sending" : "general.saving") + "…" :
                    (L(sendInvite ? "general.send" : "general.save") + (hasChanged ? " *" : ""))}
            </Button>
            <Button color={"error"}
                    onClick={onReset}
                    disabled={isSaving}
                    startIcon={<RestartAlt />}
                    variant={"outlined"} title={L("general.reset")}>
                {L("general.reset")}
            </Button>
        </ButtonBar>
    </ViewContent>
}
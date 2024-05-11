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
    FormGroup as MuiFormGroup, Autocomplete, Chip
} from "@mui/material";
import {LocaleContext} from "shared/locale";
import * as React from "react";
import ViewContent from "../../elements/view-content";
import FormGroup from "../../elements/form-group";
import ButtonBar from "../../elements/button-bar";
import {Delete, RestartAlt, Save, Send} from "@mui/icons-material";
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
    const [groups, setGroups] = useState([]);
    const [groupInput, setGroupInput] = useState("");

    // ui
    const [hasChanged, setChanged] = useState(isNewUser);
    const [isSaving, setSaving] = useState(false);
    const [sendInvite, setSetInvite] = useState(isNewUser);

    useEffect(() => {
        requestModules(api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchGroups = useCallback(() => {
        api.searchGroups(groupInput, user?.groups?.map(group => group.id)).then((res) => {
            if (res.success) {
                setGroups(res.groups);
            } else {
                showDialog(res.msg, L("account.search_groups_error"));
            }
        });
    }, [api, showDialog, user?.groups, groupInput]);

    const onFetchUser = useCallback((force = false) => {
        if (!isNewUser && (force || fetchUser)) {
            setFetchUser(false);
            api.getUser(userId).then((res) => {
                if (!res.success) {
                    showDialog(res.msg, L("account.get_user_error"));
                    if (user === null) {
                        navigate("/admin/users");
                    }
                } else {
                    setUser({...res.user, groups: Object.values(res.user.groups)});
                }
            });
        }
    }, [api, showDialog, fetchUser, isNewUser, userId, user]);

    const onReset = useCallback(() => {
        if (isNewUser) {
            setUser({...initialUser});
        } else {
            onFetchUser(true);
            setChanged(false);
        }
    }, [isNewUser, onFetchUser]);

    const onSaveUser = useCallback(() => {
        if (!isSaving) {
            let groupIds = user.groups.map(group => group.id);
            setSaving(true);
            if (isNewUser) {
                if (sendInvite) {
                    api.inviteUser(user.name, user.fullName, user.email, groupIds).then(res => {
                        setSaving(false);
                        if (res.success) {
                            setChanged(false);
                            navigate("/admin/user/" + res.userId);
                        } else {
                            showDialog(res.msg, L("account.invite_user_error"));
                        }
                    });
                } else {
                    api.createUser(user.name, user.fullName, user.email, groupIds,
                        user.password, user.passwordConfirm
                    ).then(res => {
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
                    userId, user.name, user.fullName, user.email, user.password,
                    groupIds, user.confirmed, user.active
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

    }, [isSaving, sendInvite, isNewUser, userId, showDialog, user]);

    const onChangeValue = useCallback((name, value) => {
        setUser({...user, [name]: value});
        setChanged(true);
    }, [user]);

    const onDeleteUser = useCallback(() => {
        api.deleteUser(userId).then(res => {
           if (res.success) {
               navigate("/admin/users");
           } else {
                showDialog(res.msg, L("account.delete_user_error"));
           }
        });
    }, [api, showDialog, userId]);

    useEffect(() => {
        if (!isNewUser) {
            onFetchUser(true);
        }
    }, []);

    useEffect(() => {
        onFetchGroups();
    }, [groupInput, user?.groups]);

    if (user === null) {
        return <CircularProgress />
    }

    return <ViewContent title={L(isNewUser ? "account.new_user" : "account.edit_user")} path={[
        <Link key={"dashboard"} to={"/admin/dashboard"}>Home</Link>,
        <Link key={"users"} to={"/admin/users"}>User</Link>,
        <span key={"action"}>{isNewUser ? L("general.new") : L("general.edit")}</span>
    ]}>
        <Grid container>
            <Grid item xs={12} mt={1} mb={1}>
                <Button variant={"outlined"} color={"error"} size={"small"}
                        startIcon={<Delete />}
                        disabled={isNewUser || !api.hasPermission("user/delete") || user.id === api.user.id}
                        onClick={() => showDialog(
                            L("account.delete_user_text"),
                            L("account.delete_user_title"),
                            [L("general.cancel"), L("general.confirm")],
                            (buttonIndex) => buttonIndex === 1 ? onDeleteUser() : true)
                        }
                        >
                    {L("general.delete")}
                </Button>
            </Grid>
            <Grid item xs={12} lg={6}>
                <FormGroup>
                    <FormLabel>{L("account.name")}</FormLabel>
                    <FormControl>
                        <TextField size={"small"} variant={"outlined"}
                                   value={user.name}
                                   onChange={e => onChangeValue("name", e.target.value)} />
                    </FormControl>
                </FormGroup>
                <FormGroup>
                    <FormLabel>{L("account.full_name")}</FormLabel>
                    <FormControl>
                        <TextField size={"small"} variant={"outlined"}
                                   value={user.fullName}
                                   onChange={e => onChangeValue("fullName", e.target.value)} />
                    </FormControl>
                </FormGroup>
                <FormGroup>
                    <FormLabel>{L("account.email")}</FormLabel>
                    <FormControl>
                        <TextField size={"small"} variant={"outlined"}
                                   value={user.email ?? ""}
                                   type={"email"}
                                   onChange={e => onChangeValue("email", e.target.value)} />
                    </FormControl>
                </FormGroup>
                <FormGroup>
                    <FormLabel>{L("account.groups")}</FormLabel>
                    <Autocomplete
                        options={Object.values(groups || {})}
                        getOptionLabel={group => group.name}
                        getOptionKey={group => group.id}
                        filterOptions={(options) => options}
                        clearOnBlur={true}
                        clearOnEscape
                        freeSolo
                        multiple
                        value={user.groups}
                        inputValue={groupInput}
                        onChange={(e, v) => onChangeValue("groups", v)}
                        onInputChange={e => setGroupInput((!e || e.target.value === 0) ? "" : e.target.value) }
                        renderTags={(values, props) =>
                            values.map((option, index) => {
                                return <Chip label={option.name}
                                             style={{backgroundColor: option.color}}
                                             {...props({index})} />
                            })
                        }
                        renderInput={(params) => <TextField {...params}
                                                            onBlur={() => setGroupInput("")} />}
                    />
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
                                           onChange={e => onChangeValue("password", e.target.value)} />
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
                                               onChange={e => onChangeValue("password", e.target.value)} />
                                </FormControl>
                            </FormGroup>
                            <FormGroup>
                                <FormLabel>{L("account.password_confirm")}</FormLabel>
                                <FormControl>
                                    <TextField size={"small"} variant={"outlined"}
                                               value={user.passwordConfirm}
                                               type={"password"}
                                               onChange={e => onChangeValue("passwordConfirm", e.target.value)} />
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
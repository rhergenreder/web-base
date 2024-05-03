import {Link, useNavigate, useParams} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {
    Box,
    Button,
    Checkbox,
    CircularProgress,
    FormControl,
    FormControlLabel,
    FormLabel,
    TextField
} from "@mui/material";
import {LocaleContext} from "shared/locale";
import * as React from "react";
import ViewContent from "../../elements/view-content";
import FormGroup from "../../elements/form-group";
import ButtonBar from "../../elements/button-bar";
import {RestartAlt, Save} from "@mui/icons-material";
import {parseBool} from "shared/util";
import SpacedFormGroup from "../../elements/form-group";

export default function UserEditView(props) {

    // meta
    const { api, showDialog } = props;
    const { userId } = useParams();
    const navigate = useNavigate();

    // data
    const isNewUser = userId === "new";
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const [fetchUser, setFetchUser] = useState(!isNewUser);
    const [user, setUser] = useState(isNewUser ? {
        name: "",
        fullName: "",
        email: "",
        password: "",
        groups: [],
        confirmed: false,
        active: true,
    } : null);

    // ui
    const [hasChanged, setChanged] = useState(isNewUser);
    const [isSaving, setSaving] = useState(false);

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onReset = useCallback(() => {

    }, []);

    const onSaveUser = useCallback(() => {

    }, []);

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

    const onChangeValue = useCallback((name, value) => {

    }, []);

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
        <Box>
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
                        <FormControlLabel
                            control={<Checkbox
                                checked={!!user.active}
                                onChange={(e, v) => onChangeValue("active", v)} />}
                            label={L("account.active")} />
                    </FormGroup>
                    <FormGroup>
                        <FormControlLabel
                            control={<Checkbox
                                checked={!!user.confirmed}
                                onChange={(e, v) => onChangeValue("confirmed", v)} />}
                            label={L("account.confirmed")} />
                    </FormGroup>
                </> : <>


                </>
            }
        </Box>
        <ButtonBar>
            <Button color={"primary"}
                    onClick={onSaveUser}
                    disabled={isSaving || !(isNewUser ? api.hasPermission("user/create") : api.hasPermission("user/edit"))}
                    startIcon={isSaving ? <CircularProgress size={14} /> : <Save />}
                    variant={"outlined"} title={L(hasChanged ? "general.unsaved_changes" : "general.save")}>
                {isSaving ? L("general.saving") + "…" : (L("general.save") + (hasChanged ? " *" : ""))}
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
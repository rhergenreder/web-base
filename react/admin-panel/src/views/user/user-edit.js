import {Link, useNavigate, useParams} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {CircularProgress} from "@mui/material";
import {LocaleContext} from "shared/locale";
import * as React from "react";
import ViewContent from "../../elements/view-content";

export default function UserEditView(props) {

    const { api, showDialog } = props;
    const { userId } = useParams();
    const navigate = useNavigate();
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
    } : null);

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

    </ViewContent>
}
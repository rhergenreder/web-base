import {Link, useNavigate, useParams} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {CircularProgress} from "@material-ui/core";
import {LocaleContext} from "shared/locale";
import * as React from "react";


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

    return <div className={"content-header"}>
        <div className={"container-fluid"}>
            <ol className={"breadcrumb"}>
                <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                <li className="breadcrumb-item active"><Link to={"/admin/users"}>User</Link></li>
                <li className="breadcrumb-item active">{ isNewUser ? "New" : "Edit" }</li>
            </ol>
        </div>
        <div className={"content"}>
            <div className={"container-fluid"}>
                <h3>{L(isNewUser ? "Create new User" : "Edit User")}</h3>
                <div className={"col-sm-12 col-lg-6"}>
                    <div className={"row"}>
                        <div className={"col-sm-6 form-group"}>
                            <label htmlFor={"username"}>{L("account.username")}</label>
                            <input type={"text"} className={"form-control"} placeholder={L("account.username")}
                                   name={"username"} id={"username"} maxLength={32} value={user.name}
                                   onChange={(e) => setUser({...user, name: e.target.value})}/>
                        </div>
                        <div className={"col-sm-6 form-group"}>
                            <label htmlFor={"fullName"}>{L("account.full_name")}</label>
                            <input type={"text"} className={"form-control"} placeholder={L("account.full_name")}
                                   name={"fullName"} id={"fullName"} maxLength={32} value={user.fullName}
                                   onChange={(e) => setUser({...user, fullName: e.target.value})}/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
}
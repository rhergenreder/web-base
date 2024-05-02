import React, {lazy, Suspense, useCallback, useState} from "react";
import {BrowserRouter, Navigate, Route, Routes} from "react-router-dom";
import Dialog from "shared/elements/dialog";
import Sidebar from "./elements/sidebar";
import Footer from "./elements/footer";
import {useContext, useEffect} from "react";
import {LocaleContext} from "shared/locale";

// views
import View404 from "./views/404";
import clsx from "clsx";
const Overview = lazy(() => import('./views/overview'));
const UserListView = lazy(() => import('./views/user/user-list'));
const UserEditView = lazy(() => import('./views/user/user-edit'));
const GroupListView = lazy(() => import('./views/group/group-list'));
const EditGroupView = lazy(() => import('./views/group/group-edit'));
const LogView = lazy(() => import("./views/log-view"));
const AccessControlList = lazy(() => import("./views/access-control-list"));
const RouteListView = lazy(() => import("./views/route/route-list"));
const RouteEditView = lazy(() => import("./views/route/route-edit"));
const SettingsView = lazy(() => import("./views/settings/settings"));
const ProfileView = lazy(() => import("./views/profile/profile"));

export default function AdminDashboard(props) {

    const api = props.api;
    const info = props.info;
    const [dialog, setDialog] = useState({show: false});

    const {currentLocale, requestModules, translate: L} = useContext(LocaleContext);

    const hideDialog = useCallback(() => {
        setDialog({show: false});
    }, []);

    const showDialog = useCallback((message, title, options=["Close"], onOption = null) => {
        setDialog({
            show: true,
            message: message,
            title: title,
            options: options,
            onOption: onOption,
            onClose: hideDialog
        });
    }, []);

    useEffect(() => {
        requestModules(api, ["general", "admin", "account"], currentLocale).then(data => {
            if (!data.success) {
                alert(data.msg);
            }
        });
    }, [currentLocale]);

    const controlObj = {
        ...props,
        showDialog: showDialog,
        hideDialog: hideDialog
    };

    return <BrowserRouter>
        <Sidebar {...controlObj}>
            <Suspense fallback={<div>{L("general.loading")}... </div>}>
                <Routes>
                    <Route path={"/admin"} element={<Navigate to={"/admin/dashboard"} />}/>
                    <Route path={"/admin/dashboard"} element={<Overview {...controlObj} />}/>
                    <Route path={"/admin/users"} element={<UserListView {...controlObj} />}/>
                    <Route path={"/admin/user/:userId"} element={<UserEditView {...controlObj} />}/>
                    <Route path={"/admin/groups"} element={<GroupListView {...controlObj} />}/>
                    <Route path={"/admin/group/:groupId"} element={<EditGroupView {...controlObj} />}/>
                    <Route path={"/admin/logs"} element={<LogView {...controlObj} />}/>
                    <Route path={"/admin/permissions"} element={<AccessControlList {...controlObj} />}/>
                    <Route path={"/admin/routes"} element={<RouteListView {...controlObj} />}/>
                    <Route path={"/admin/routes/:routeId"} element={<RouteEditView {...controlObj} />}/>
                    <Route path={"/admin/settings"} element={<SettingsView {...controlObj} />}/>
                    <Route path={"/admin/profile"} element={<ProfileView {...controlObj} />}/>
                    <Route path={"*"} element={<View404 />} />
                </Routes>
            </Suspense>
            <Footer info={info} />
        </Sidebar>
        <Dialog {...dialog}/>
    </BrowserRouter>
}
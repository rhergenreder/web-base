import React, {lazy, Suspense, useCallback, useState} from "react";
import {BrowserRouter, Route, Routes} from "react-router-dom";
import Header from "./elements/header";
import Sidebar from "./elements/sidebar";
import Dialog from "./elements/dialog";
import Footer from "./elements/footer";
import {useContext, useEffect} from "react";
import {LocaleContext} from "shared/locale";

// css
import './res/adminlte.min.css';

// views
import View404 from "./views/404";
const Overview = lazy(() => import('./views/overview'));
const UserListView = lazy(() => import('./views/user/user-list'));
const UserEditView = lazy(() => import('./views/user/user-edit'));
const GroupListView = lazy(() => import('./views/group-list'));
const EditGroupView = lazy(() => import('./views/group-edit'));


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
            show: true, message:
            message,
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
        <Header {...controlObj} />
        <Sidebar {...controlObj} />
        <div className={"wrapper"}>
            <div className={"content-wrapper p-2"}>
                <section className={"content"}>
                    <Suspense fallback={<div>{L("general.loading")}... </div>}>
                        <Routes>
                            <Route path={"/admin"} element={<Overview {...controlObj} />}/>
                            <Route path={"/admin/dashboard"} element={<Overview {...controlObj} />}/>
                            <Route path={"/admin/users"} element={<UserListView {...controlObj} />}/>
                            <Route path={"/admin/user/:userId"} element={<UserEditView {...controlObj} />}/>
                            <Route path={"/admin/groups"} element={<GroupListView {...controlObj} />}/>
                            <Route path={"/admin/group/:groupId"} element={<EditGroupView {...controlObj} />}/>
                            <Route path={"*"} element={<View404 />} />
                        </Routes>
                    </Suspense>
                    {/*<Route exact={true} path={"/admin/users"}><UserOverview {...this.controlObj} /></Route>
                                <Route path={"/admin/user/add"}><CreateUser {...this.controlObj} /></Route>
                                <Route path={"/admin/user/edit/:userId"} render={(props) => {
                                    let newProps = {...props, ...this.controlObj};
                                    return <EditUser {...newProps} />
                                }}/>
                                <Route path={"/admin/user/permissions"}><PermissionSettings {...this.controlObj}/></Route>
                                <Route path={"/admin/group/add"}><CreateGroup {...this.controlObj} /></Route>
                                <Route exact={true} path={"/admin/contact/"}><ContactRequestOverview {...this.controlObj} /></Route>
                                <Route path={"/admin/visitors"}><Visitors {...this.controlObj} /></Route>
                                <Route path={"/admin/logs"}><Logs {...this.controlObj} notifications={this.state.notifications} /></Route>
                                <Route path={"/admin/settings"}><Settings {...this.controlObj} /></Route>
                                <Route path={"/admin/pages"}><PageOverview {...this.controlObj} /></Route>
                                <Route path={"/admin/help"}><HelpPage {...this.controlObj} /></Route>
                                <Route path={"*"}><View404 /></Route>*/}
                    <Dialog {...dialog}/>
                </section>
            </div>
        </div>
        <Footer info={info} />
    </BrowserRouter>
}
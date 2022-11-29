import React from 'react';
import './res/adminlte.min.css';
import './res/index.css';
import API from "shared/api";
import Icon from "shared/elements/icon";
import {BrowserRouter, Route, Routes} from "react-router-dom";
import Dialog from "./elements/dialog";
import Footer from "./elements/footer";
import Header from "./elements/header";
import Sidebar from "./elements/sidebar";
import LoginForm from "./views/login";
import {Alert} from "@material-ui/lab";
import {Button} from "@material-ui/core";

export default class AdminDashboard extends React.Component {

    constructor(props) {
        super(props);
        this.api = new API();
        this.state = {
            loaded: false,
            dialog: { onClose: () => this.hideDialog() },
            info: { },
            error: null,
        };
    }

    onUpdate() {
    }

    showDialog(message, title, options=["Close"], onOption = null) {
        const props = { show: true, message: message, title: title, options: options, onOption: onOption };
        this.setState({ ...this.state, dialog: { ...this.state.dialog, ...props } });
    }

    hideDialog() {
        this.setState({ ...this.state, dialog: { ...this.state.dialog, show: false } });
    }

    onInit() {
        this.setState({ ...this.state, loaded: false, error: null });
        this.api.info().then(data => {
            if (data.success) {
                this.setState({...this.state, info: data.info })
                this.api.fetchUser().then(data => {
                    if (data.success) {
                        setInterval(this.onUpdate.bind(this), 60*1000);
                        this.setState({...this.state, loaded: true});
                    } else {
                        this.setState({ ...this.state, error: data.msg })
                    }
                });
            } else {
                this.setState({ ...this.state, error: data.msg })
            }
        });
    }

    componentDidMount() {
        this.onInit();
    }

    onUpdateLocale() {
        this.setState({ ...this.state, locale: currentLocale })
    }

    onLogin(username, password, rememberMe, callback) {
        this.setState({ ...this.state, error: "" });
        return this.api.login(username, password, rememberMe).then((res) => {
            if (res.success) {
                this.api.fetchUser().then(() => {
                    this.setState({ ...this.state, user: res });
                    callback(res);
                })
            } else {
                this.setState({ ...this.state, error: res.msg });
                callback(res);
            }
        });
    }

    onLogout(callback) {
        this.api.logout().then(() => {
            this.api.loggedIn = false;
            this.setState({ ...this.state, user: { } })
            if (callback) callback();
        });
    }

    onTotp2FA(code, callback) {
        this.setState({ ...this.state, error: "" });
        return this.api.verifyTotp2FA(code).then((res) => {
            if (res.success) {
                this.api.fetchUser().then(() => {
                    this.setState({ ...this.state, user: res });
                    callback(res);
                })
            } else {
                this.setState({ ...this.state, error: res.msg });
                callback(res);
            }
        });
    }

    onKey2FA(credentialID, clientDataJson, authData, signature, callback) {
        this.setState({ ...this.state, error: "" });
        return this.api.verifyKey2FA(credentialID, clientDataJson, authData, signature).then((res) => {
            if (res.success) {
                this.api.fetchUser().then(() => {
                    this.setState({ ...this.state, user: res });
                    callback(res);
                })
            } else {
                this.setState({ ...this.state, error: res.msg });
                callback(res);
            }
        });
    }

    render() {

        if (!this.state.loaded) {
            if (this.state.error) {
                return <Alert severity={"error"} title={"An error occurred"}>
                    <div>{this.state.error}</div>
                    <Button type={"button"} variant={"outlined"} onClick={() => this.onInit()}>
                        Retry
                    </Button>
                </Alert>
            } else {
                return <b>Loading… <Icon icon={"spinner"}/></b>
            }
        }

        this.controlObj = {
            showDialog: this.showDialog.bind(this),
            api: this.api,
            info: this.state.info,
            onUpdateLocale: this.onUpdateLocale.bind(this),
            onLogout: this.onLogout.bind(this),
            onLogin: this.onLogin.bind(this),
            onTotp2FA: this.onTotp2FA.bind(this),
            onKey2FA: this.onKey2FA.bind(this),
        };

        if (!this.api.loggedIn) {
            return <LoginForm {...this.controlObj}/>
        }

        return <BrowserRouter>
            <Header {...this.controlObj} />
            <Sidebar {...this.controlObj} />
            <div className={"content-wrapper p-2"}>
                <section className={"content"}>
                    <Routes>
                        {/*<Route path={"/admin/dashboard"}><Overview {...this.controlObj} notifications={this.state.notifications} /></Route>
                        <Route exact={true} path={"/admin/users"}><UserOverview {...this.controlObj} /></Route>
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
                    </Routes>
                    <Dialog {...this.state.dialog}/>
                </section>
            </div>
            <Footer />
        </BrowserRouter>
    }
}
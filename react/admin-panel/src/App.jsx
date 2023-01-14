import React, {useCallback, useContext, useEffect, useMemo, useState} from 'react';
import API from "shared/api";
import Icon from "shared/elements/icon";
import LoginForm from "shared/views/login";
import {Alert} from "@material-ui/lab";
import {Button} from "@material-ui/core";
import {LocaleContext} from "shared/locale";
import AdminDashboard from "./AdminDashboard";

export default function App() {

    const api = useMemo(() => new API(), []);

    const [user, setUser] = useState(null);
    const [loaded, setLoaded] = useState(false);
    const [info, setInfo] = useState({});
    const [error, setError] = useState(null);
    const {translate: L} = useContext(LocaleContext);

    const fetchUser = useCallback(() => {
        api.fetchUser().then(data => {
            if (data.success) {
                setUser(data.user || null);
                setLoaded(true);
            } else {
                setError(data.msg);
            }
        });
    }, [api]);

    const onInit = useCallback((force = false) => {
        if (loaded && !force) {
            return;
        }

        setError(false);
        setLoaded(false);
        api.getLanguageEntries("general").then(data => {
            if (data.success) {
                api.info().then(data => {
                    if (data.success) {
                        setInfo(data.info);
                        fetchUser();
                    } else {
                        setError(data.msg);
                    }
                });
            } else {
                setError(data.msg);
            }
        });
    }, [api, loaded, fetchUser]);

    useEffect(() => {
        onInit();
    }, []);

    /*
    const onTotp2FA = useCallback((code, callback) => {
        this.setState({ ...this.state, error: "" });
        return this.api.verifyTotp2FA(code).then((res) => {
            if (res.success) {
                this.api.fetchUser().then(() => {
                    this.setState({ ...this.state, user: res });
                    callback(res);
                })
            } else {
                callback(res);
            }
        });
    }, [api]);

    onKey2FA(credentialID, clientDataJson, authData, signature, callback) {
        this.setState({ ...this.state, error: "" });
        return this.api.verifyKey2FA(credentialID, clientDataJson, authData, signature).then((res) => {
            if (res.success) {
                this.api.fetchUser().then(() => {
                    this.setState({ ...this.state, user: res });
                    callback(res);
                })
            } else {
                callback(res);
            }
        });
    }

     */

    if (!loaded) {
        if (error) {
            return <Alert severity={"error"} title={L("general.error_occurred")}>
                <div>{error}</div>
                <Button type={"button"} variant={"outlined"} onClick={() => onInit(true)}>
                    Retry
                </Button>
            </Alert>
        } else {
            return <b>{L("general.loading")}… <Icon icon={"spinner"}/></b>
        }
    } else if (!user || !api.loggedIn) {
        return <LoginForm api={api} info={info} onLogin={fetchUser} />
    } else {
        return <AdminDashboard api={api} info={info} />
    }
}
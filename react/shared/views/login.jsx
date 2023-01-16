import {
    Box,
    Button,
    Checkbox, CircularProgress, Container,
    FormControlLabel,
    Grid,
    Link,
    TextField,
    Typography
} from "@material-ui/core";

import {makeStyles} from '@material-ui/core/styles';
import {Alert} from '@material-ui/lab';
import React, {useCallback, useContext, useEffect, useState} from "react";
import ReplayIcon from '@material-ui/icons/Replay';
import LanguageSelection from "../elements/language-selection";
import {decodeText, encodeText, getParameter, removeParameter} from "shared/util";
import Icon from "shared/elements/icon";
import {LocaleContext} from "shared/locale";

const useStyles = makeStyles((theme) => ({
    paper: {
        marginTop: theme.spacing(8),
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
    },
    avatar: {
        margin: theme.spacing(2),
        width: "60px",
        height: "60px"
    },
    form: {
        width: '100%', // Fix IE 11 issue.
        marginTop: theme.spacing(1),
    },
    submit: {
        margin: theme.spacing(3, 0, 2),
    },
    logo: {
        marginRight: theme.spacing(3)
    },
    headline: {
        width: "100%",
    },
    container: {
        marginTop: theme.spacing(5),
        paddingBottom: theme.spacing(1),
        borderColor: theme.palette.primary.main,
        borderStyle: "solid",
        borderWidth: 1,
        borderRadius: 5
    },
    buttons2FA: {
        marginTop: theme.spacing(1),
        marginBottom: theme.spacing(1),
    },
    error2FA: {
        marginTop: theme.spacing(2),
        marginBottom: theme.spacing(2),
        "& > div": {
            fontSize: 16
        },
        "& > button": {
            marginTop: theme.spacing(1)
        }
    }
}));

export default function LoginForm(props) {

    const api = props.api;
    const classes = useStyles();

    // inputs
    let [username, setUsername] = useState("");
    let [password, setPassword] = useState("");
    let [rememberMe, setRememberMe] = useState(true);
    let [emailConfirmed, setEmailConfirmed] = useState(null);
    let [tfaCode, set2FACode] = useState("");

    // 2fa
    // 0: not sent, 1: sent, 2: retry
    let [tfaToken, set2FAToken] = useState(api.user?.twoFactorToken || { authenticated: false, type: null, step: 0 });
    let [error, setError] = useState("");

    const abortController = new AbortController();
    const abortSignal = abortController.signal;

    // state
    let [isLoggingIn, setLoggingIn] = useState(false);
    let [loaded, setLoaded] = useState(false);

    const {translate: L, currentLocale, requestModules} = useContext(LocaleContext);

    const onUpdateLocale = useCallback(() => {
        requestModules(api, ["general", "account"], currentLocale).then(data => {
            setLoaded(true);
            if (!data.success) {
                alert(data.msg);
            }
        });
    }, [currentLocale]);

    useEffect(() => {
        onUpdateLocale();
    }, [currentLocale]);

    const onLogin = useCallback(() => {
        if (!isLoggingIn) {
            setError("");
            setLoggingIn(true);
            removeParameter("success");
            api.login(username, password, rememberMe).then((res) => {
                let twoFactorToken = res.twoFactorToken || { };
                set2FAToken({ ...twoFactorToken, authenticated: false, step: 0, error: "" });
                setLoggingIn(false);
                setPassword("");
                if (!res.success) {
                    setEmailConfirmed(res.emailConfirmed);
                    setError(res.msg);
                } else if (!twoFactorToken.type) {
                    props.onLogin();
                }
            });
        }
    }, [api, isLoggingIn, password, props, rememberMe, username]);

    const onSubmit2FA = useCallback(() => {
        setLoggingIn(true);
        api.verifyTotp2FA(tfaCode).then((res) => {
            setLoggingIn(false);
            if (res.success) {
                set2FAToken({ ...tfaToken, authenticated: true });
                props.onLogin();
            } else {
                set2FAToken({ ...tfaToken, step: 2, error: res.msg });
            }
        });
    }, [tfaCode, tfaToken, props]);

    const onCancel2FA = useCallback(() => {
        abortController.abort();
        props.onLogout();
        set2FAToken({authenticated: false, step: 0, error: ""});
    }, [props, abortController]);

    useEffect(() => {
        if (!api.loggedIn) {
            return;
        }

        if (!tfaToken || !tfaToken.confirmed || tfaToken.authenticated || tfaToken.type !== "fido") {
            return;
        }

        let step = tfaToken.step || 0;
        if (step !== 0) {
            return;
        }

        set2FAToken({ ...tfaToken, step: 1, error: "" });
        navigator.credentials.get({
            publicKey: {
                challenge: encodeText(window.atob(tfaToken.challenge)),
                allowCredentials: [{
                    id: encodeText(window.atob(tfaToken.credentialID)),
                    type: "public-key",
                }],
                userVerification: "discouraged",
            },
            signal: abortSignal
        }).then((res) => {
            let credentialID = res.id;
            let clientDataJson = decodeText(res.response.clientDataJSON);
            let authData = window.btoa(decodeText(res.response.authenticatorData));
            let signature = window.btoa(decodeText(res.response.signature));
            api.verifyKey2FA(credentialID, clientDataJson, authData, signature).then((res) => {
                if (!res.success) {
                    set2FAToken({ ...tfaToken, step: 2, error: res.msg });
                } else {
                    props.onLogin();
                }
            });
        }).catch(e => {
            set2FAToken({ ...tfaToken, step: 2, error: e.toString() });
        });
    }, [api.loggedIn, tfaToken, props.onLogin, props.onKey2FA, abortSignal]);

    const createForm = () => {

        // 2FA
        if (api.loggedIn && tfaToken.type) {

            if (tfaToken.type === "totp") {
                return <>
                    <div>{L("account.2fa_title")}:</div>
                    <TextField
                        variant={"outlined"} margin={"normal"}
                        id={"code"} label={L("account.6_digit_code")} name={"code"}
                        autoComplete={"code"}
                        required fullWidth autoFocus
                        value={tfaCode} onChange={(e) => set2FACode(e.target.value)}
                    />
                    {
                        tfaToken.error ? <Alert severity="error">{tfaToken.error}</Alert> : <></>
                    }
                    <Grid container spacing={2} className={classes.buttons2FA}>
                        <Grid item xs={6}>
                            <Button
                                fullWidth variant={"contained"}
                                color={"inherit"} size={"medium"}
                                disabled={isLoggingIn}
                                onClick={onCancel2FA}>
                                {L("general.go_back")}
                            </Button>
                        </Grid>
                        <Grid item xs={6}>
                            <Button
                                type="submit" fullWidth variant="contained"
                                color="primary" size={"medium"}
                                disabled={isLoggingIn || tfaToken.type !== "totp"}
                                onClick={onSubmit2FA}>
                                {isLoggingIn ?
                                    <>{L("general.submitting")}… <CircularProgress size={15}/></> :
                                    L("general.submit")
                                }
                            </Button>
                        </Grid>
                    </Grid>
                </>
            } else if (tfaToken.type === "fido") {
                return <>
                    <div>{L("account.2fa_title")}:</div>
                    <br />
                    {L("account.2fa_text")}
                    <Box mt={2} textAlign={"center"}>
                        {tfaToken.step !== 2
                            ? <CircularProgress/>
                            : <div className={classes.error2FA}>
                                <div><b>{L("general.something_went_wrong")}:</b><br />{tfaToken.error}</div>
                                <Button onClick={() => set2FAToken({ ...tfaToken, step: 0, error: "" })}
                                        variant={"outlined"} color={"secondary"} size={"small"}>
                                    <ReplayIcon />&nbsp;{L("general.retry")}
                                </Button>
                            </div>
                        }
                    </Box>
                    <Grid container spacing={2} className={classes.buttons2FA}>
                        <Grid item xs={6}>
                            <Button
                                fullWidth variant={"contained"}
                                color={"inherit"} size={"medium"}
                                disabled={isLoggingIn}
                                onClick={onCancel2FA}>
                                {L("general.go_back")}
                            </Button>
                        </Grid>
                        <Grid item xs={6}>
                            <Button
                                type="submit" fullWidth variant="contained"
                                color="primary" size={"medium"}
                                disabled={isLoggingIn || tfaToken.type !== "totp"}
                                onClick={onSubmit2FA}>
                                {isLoggingIn ?
                                    <>{L("general.submitting")}… <CircularProgress size={15}/></> :
                                    L("general.submit")
                                }
                            </Button>
                        </Grid>
                    </Grid>
                </>
            }
        }

        return <>
                <TextField
                    variant="outlined" margin="normal"
                    id="username" label={L("account.username")} name="username"
                    autoComplete="username" disabled={isLoggingIn}
                    required fullWidth autoFocus
                    value={username} onChange={(e) => setUsername(e.target.value)}
                />
                <TextField
                    variant="outlined" margin="normal"
                    name="password" label={L("account.password")} type="password" id="password"
                    autoComplete="current-password"
                    required fullWidth disabled={isLoggingIn}
                    value={password} onChange={(e) => setPassword(e.target.value)}
                />
                <FormControlLabel
                    control={<Checkbox value="remember" color="primary"/>}
                    label={L("account.remember_me")}
                    checked={rememberMe} onClick={(e) => setRememberMe(!rememberMe)}
                />
                {
                    error
                        ? <Alert severity="error">
                            {error}
                            {emailConfirmed === false
                                ? <> <Link href={"/resendConfirmation"}>Click here</Link> to resend the confirmation email.</>
                                : <></>
                            }
                        </Alert>
                        : (successMessage
                            ? <Alert severity="success">{successMessage}</Alert>
                            : <></>)
                }
                <Button
                    type={"submit"} fullWidth variant={"contained"}
                    color={"primary"} className={classes.submit}
                    size={"large"}
                    disabled={isLoggingIn}
                    onClick={onLogin}>
                    {isLoggingIn ?
                        <>{L("account.signing_in")}… <CircularProgress size={15}/></> :
                        L("account.sign_in")
                    }
                </Button>
                <Grid container>
                    <Grid item xs>
                        <Link href="/resetPassword" variant="body2">
                            {L("account.forgot_password")}
                        </Link>
                    </Grid>
                    { props.info.registrationAllowed ?
                        <Grid item>
                            <Link href="/register" variant="body2">
                                {L("account.register_text")}
                            </Link>
                        </Grid> : <></>
                    }
                </Grid>
            </>
    }

    if (!loaded) {
        return <b>{L("general.loading")}… <Icon icon={"spinner"}/></b>
    }

    let successMessage = getParameter("success");
    return <Container maxWidth={"xs"} className={classes.container}>
        <div className={classes.paper}>
            <div className={classes.headline}>
                <Typography component="h1" variant="h4">
                    <img src={"/img/icons/logo.png"} alt={"Logo"} height={48} className={classes.logo}/>
                    {props.info.siteName}
                </Typography>
            </div>
            <form className={classes.form} onSubmit={(e) => e.preventDefault()}>
                { createForm() }
                <LanguageSelection api={api} />
            </form>
        </div>
    </Container>
}

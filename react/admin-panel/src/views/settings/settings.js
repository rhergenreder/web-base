import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    Box, Button,
    CircularProgress, FormControl,
    FormGroup, FormLabel, Grid, IconButton,
    Paper,
    Tab,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableContainer,
    TableRow,
    Tabs, TextField,
} from "@mui/material";
import {Link} from "react-router-dom";
import {
    Add,
    Delete, DownloadDone,
    LibraryBooks,
    Mail,
    RestartAlt,
    Save,
    Send,
    SettingsApplications, SmartToy, Storage
} from "@mui/icons-material";
import TIME_ZONES from "shared/time-zones";
import ButtonBar from "../../elements/button-bar";
import SettingsTextValues from "./input-text-values";
import SettingsCheckBox from "./input-check-box";
import SettingsNumberInput from "./input-number";
import SettingsPasswordInput from "./input-password";
import SettingsTextInput from "./input-text";
import SettingsSelection from "./input-selection";
import ViewContent from "../../elements/view-content";

export default function SettingsView(props) {

    // TODO: website-logo (?), mail_contact, mail_contact_gpg_key_id

    // meta
    const api = props.api;
    const showDialog = props.showDialog;
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const KNOWN_SETTING_KEYS = {
      "general": [
          "base_url",
          "site_name",
          "user_registration_enabled",
          "time_zone",
          "allowed_extensions",
          "trusted_domains",
      ],
      "mail": [
          "mail_enabled",
          "mail_footer",
          "mail_from",
          "mail_host",
          "mail_port",
          "mail_username",
          "mail_password",
          "mail_async",
      ],
      "captcha": [
          "captcha_provider",
          "captcha_secret_key",
          "captcha_site_key",
      ],
      "redis": [
          "rate_limiting_enabled",
          "redis_host",
          "redis_port",
          "redis_password"
      ]
    };

    // data
    const [fetchSettings, setFetchSettings] = useState(true);
    const [settings, setSettings] = useState(null);
    const [uncategorizedKeys, setUncategorizedKeys] = useState([]);

    // ui
    const [selectedTab, setSelectedTab] = useState("general");
    const [hasChanged, setChanged] = useState(false);
    const [isSaving, setSaving] = useState(false);
    const [newKey, setNewKey] = useState("");
    const [testMailAddress, setTestMailAddress] = useState("");
    const [isSending, setSending] = useState(false);

    const isUncategorized = (key) => {
        return !(Object.values(KNOWN_SETTING_KEYS).reduce((acc, arr) => {
            return [ ...acc, ...arr ];
        }, [])).includes(key);
    }

    useEffect(() => {
        requestModules(props.api, ["general", "settings"], currentLocale).then(data => {
            if (!data.success) {
                showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchSettings = useCallback((force = false) => {
        if (fetchSettings || force) {
            setFetchSettings(false);
            api.getSettings().then(data => {
                if (!data.success) {
                    showDialog(data.msg, L("settings.fetch_settings_error"));
                } else {
                    setSettings(Object.keys(data.settings)
                        .reduce((obj, key) => {
                            obj[key] = data.settings[key];
                            return obj;
                        }, {})
                    );
                    setUncategorizedKeys(Object.keys(data.settings).filter(key => isUncategorized(key)));
                }
            });
        }
    }, [api, showDialog, fetchSettings]);

    useEffect(() => {
        onFetchSettings();
    }, [fetchSettings]);

    const onChangeValue = useCallback((key, value) => {
        setChanged(true);
        setSettings({...settings, [key]: value});
    }, [settings]);

    const onSaveSettings = useCallback(() => {
        setSaving(true);
        api.saveSettings(settings).then(data => {
            setSaving(false);
            if (data.success) {
                showDialog(L("settings.save_settings_success"), L("general.success"));
                setChanged(false);
            } else {
                showDialog(data.msg, L("settings.save_settings_error"));
            }
        });
    }, [api, showDialog, settings]);

    const onDeleteKey = useCallback(key => {
        if (key && settings.hasOwnProperty(key)) {
            let index = uncategorizedKeys.indexOf(key);
            if (index !== -1) {
                let newUncategorizedKeys = [...uncategorizedKeys];
                newUncategorizedKeys.splice(index, 1);
                setUncategorizedKeys(newUncategorizedKeys);
            }
            setChanged(true);
            setSettings({...settings, [key]: null});
        }
    }, [settings, uncategorizedKeys]);

    const onAddKey = useCallback(key => {
        if (key) {
            if (!isUncategorized(key) || !settings.hasOwnProperty(key) || settings[key] === null) {
                setChanged(true);
                setSettings({...settings, [key]: ""});
                setUncategorizedKeys([...uncategorizedKeys, key]);
                setNewKey("");
            } else {
                showDialog("This key is already defined", L("general.error"));
            }
        }
    }, [settings, uncategorizedKeys, showDialog]);

    const onChangeKey = useCallback((oldKey, newKey) => {
        if (settings.hasOwnProperty(oldKey) && !settings.hasOwnProperty(newKey)) {
            let newSettings = {...settings, [newKey]: settings[oldKey]};
            delete newSettings[oldKey];
            setChanged(true);
            setSettings(newSettings);
        }
    }, [settings]);

    const onSendTestMail = useCallback(() => {
        if (!isSending) {
            setSending(true);
            api.sendTestMail(testMailAddress).then(data => {
                setSending(false);
               if (!data.success) {
                   showDialog(<>
                       {data.msg} <br />
                       <code>
                           {data.output}
                       </code>
                   </>, L("settings.send_test_email_error"));
               } else {
                   showDialog(L("settings.send_test_email_success"), L("general.success"));
                   setTestMailAddress("");
               }
            });
        }
    }, [api, showDialog, testMailAddress, isSending]);

    const onTestRedis = useCallback(data => {
        api.testRedis().then(data => {
            if (!data.success) {
                showDialog(data.msg, L("settings.redis_test_error"));
            } else {
                showDialog(L("settings.redis_test_success"), L("general.success"));
            }
        });
    }, [api, showDialog]);

    const onReset = useCallback(() => {
        setFetchSettings(true);
        setNewKey("");
        setChanged(false);
    }, []);

    const getInputProps = (key_name, disabled = false, props = {}) => {
        return {
            key: "form-" + key_name,
            key_name: key_name,
            value: settings[key_name],
            disabled: disabled,
            onChangeValue: v => { setChanged(true); setSettings({...settings, [key_name]: v}) },
            ...props
        };
    }

    const renderTextInput = (key_name, disabled=false, props={}) => {
        return <SettingsTextInput {...getInputProps(key_name, disabled, props)} />
    }

    const renderPasswordInput = (key_name, disabled=false, props={}) => {
        return <SettingsPasswordInput {...getInputProps(key_name, disabled, props)} />
    }

    const renderNumberInput = (key_name, minValue, maxValue, disabled=false, props={}) => {
        return <SettingsNumberInput minValue={minValue} maxValue={maxValue} {...getInputProps(key_name, disabled, props)} />
    }

    const renderCheckBox = (key_name, disabled=false, props={}) => {
        return <SettingsCheckBox {...getInputProps(key_name, disabled, props)} />
    }

    const renderSelection = (key_name, options, disabled=false, props={}) => {
        return <SettingsSelection options={options} {...getInputProps(key_name, disabled, props)} />
    }

    const renderTextValuesInput = (key_name, disabled=false, props={}) => {
        return <SettingsTextValues {...getInputProps(key_name, disabled, props)} />
    }

    const renderTab = () => {
        if (selectedTab === "general") {
            return [
                renderTextInput("site_name"),
                renderTextInput("base_url"),
                renderTextValuesInput("trusted_domains"),
                renderCheckBox("user_registration_enabled"),
                renderTextValuesInput("allowed_extensions"),
                renderSelection("time_zone", TIME_ZONES),
            ];
        } else if (selectedTab === "mail") {
            return [
                renderCheckBox("mail_enabled"),
                renderTextInput("mail_from", !settings.mail_enabled),
                renderTextInput("mail_host", !settings.mail_enabled),
                renderNumberInput("mail_port", 1, 65535, !settings.mail_enabled),
                renderTextInput("mail_username", !settings.mail_enabled),
                renderPasswordInput("mail_password", !settings.mail_enabled),
                renderTextInput("mail_footer", !settings.mail_enabled),
                renderCheckBox("mail_async", !settings.mail_enabled),
                <FormGroup key={"mail-test"}>
                    <FormLabel>{L("settings.send_test_email")}</FormLabel>
                    <FormControl disabled={!settings.mail_enabled}>
                        <Grid container spacing={1}>
                            <Grid item xs={1}>
                                <Button startIcon={isSending ? <CircularProgress size={14} /> : <Send />}
                                        variant={"outlined"} onClick={onSendTestMail}
                                        fullWidth={true}
                                        disabled={!settings.mail_enabled || isSending || !api.hasPermission("mail/test") || hasChanged}
                                        title={hasChanged ? L("general.unsaved_changes") : ""}>
                                    {isSending ? L("general.sending") + "…" : L("general.send")}
                                </Button>
                            </Grid>
                            <Grid item xs={11}>
                                <TextField disabled={!settings.mail_enabled}
                                           fullWidth={true}
                                           variant={"outlined"} value={testMailAddress}
                                           onChange={e => setTestMailAddress(e.target.value)}
                                           size={"small"} type={"email"}
                                           placeholder={L("settings.mail_address")} />
                            </Grid>
                        </Grid>
                    </FormControl>
                </FormGroup>
            ];
        } else if (selectedTab === "captcha") {
            let captchaOptions = {};
            ["disabled", "recaptcha", "hcaptcha"].reduce((map, key) => {
                map[key] = L("settings." + key);
                return map;
            }, captchaOptions);

            return [
                renderSelection("captcha_provider", captchaOptions),
                renderTextInput("captcha_site_key", settings.captcha_provider === "disabled"),
                renderPasswordInput("captcha_secret_key", settings.captcha_provider === "disabled"),
            ];
        } else if (selectedTab === "redis") {
            return [
                renderCheckBox("rate_limiting_enabled"),
                renderTextInput("redis_host", !settings.rate_limiting_enabled),
                renderNumberInput("redis_port", 1, 65535, !settings.rate_limiting_enabled),
                renderPasswordInput("redis_password", !settings.rate_limiting_enabled),
                <Button startIcon={<DownloadDone />}
                        variant={"outlined"} onClick={onTestRedis}
                        disabled={!settings.rate_limiting_enabled || !api.hasPermission("testRedis") || hasChanged}
                        title={hasChanged ? L("general.unsaved_changes") : ""}>
                    {L("settings.redis_test")}
                </Button>
            ];
        } else if (selectedTab === "uncategorized") {
            return <TableContainer component={Paper}>
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>{L("settings.key")}</TableCell>
                                <TableCell>{L("settings.value")}</TableCell>
                                <TableCell align={"center"}>{L("general.controls")}</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {uncategorizedKeys.map(key => <TableRow key={"settings-" + key}>
                                <TableCell>
                                    <TextField fullWidth={true} size={"small"} value={key}
                                               onChange={e => onChangeKey(key, e.target.value)} />
                                </TableCell>
                                <TableCell>
                                    <TextField fullWidth={true} size={"small"} value={settings[key]}
                                        onChange={e => onChangeValue(key, e.target.value)} />
                                </TableCell>
                                <TableCell align={"center"}>
                                    <IconButton onClick={() => onDeleteKey(key)}
                                        color={"secondary"}>
                                        <Delete />
                                    </IconButton>
                                </TableCell>
                            </TableRow>)}
                            <TableRow>
                                <TableCell>
                                    <TextField fullWidth={true} size={"small"} onChange={e => setNewKey(e.target.value)}
                                        onBlur={() => onAddKey(newKey)} value={newKey} />
                                </TableCell>
                                <TableCell>
                                    <TextField fullWidth={true} size={"small"} />
                                </TableCell>
                                <TableCell align={"center"}>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <Box p={1}>
                        <Button startIcon={<Add />} variant={"outlined"}
                                size={"small"}>
                                    {L("general.add")}
                                </Button>
                    </Box>
                </TableContainer>
        } else {
            return <i>Invalid tab: {selectedTab}</i>
        }
    }

    if (settings === null) {
        return <CircularProgress />
    }

    return <ViewContent title={L("settings.title")} path={[
        <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
        <span key={"settings"}>{L("settings.title")}</span>,
    ]}>
        <Tabs value={selectedTab} onChange={(e, v) => setSelectedTab(v)} component={Paper}>
            <Tab value={"general"} label={L("settings.general")}
                 icon={<SettingsApplications />} iconPosition={"start"} />
            <Tab value={"mail"} label={L("settings.mail")}
                 icon={<Mail />} iconPosition={"start"} />
            <Tab value={"captcha"} label={L("settings.captcha")}
                 icon={<SmartToy />} iconPosition={"start"} />
            <Tab value={"redis"} label={L("settings.rate_limit")}
                 icon={<Storage />} iconPosition={"start"} />
            <Tab value={"uncategorized"} label={L("settings.uncategorized")}
                 icon={<LibraryBooks />} iconPosition={"start"} />
        </Tabs>
        <Box p={2}>
            {
                renderTab()
            }
        </Box>
        <ButtonBar>
            <Button color={"primary"}
                    onClick={onSaveSettings}
                    disabled={isSaving || !api.hasPermission("settings/set")}
                    startIcon={isSaving ? <CircularProgress size={14} /> : <Save />}
                    variant={"outlined"} title={L(hasChanged ? "general.unsaved_changes" : "general.save")}>
                {isSaving ? L("general.saving") + "…" : (L("general.save") + (hasChanged ? " *" : ""))}
            </Button>
            <Button color={"secondary"}
                    onClick={onReset}
                    disabled={isSaving}
                    startIcon={<RestartAlt />}
                    variant={"outlined"} title={L("general.reset")}>
                {L("general.reset")}
            </Button>
        </ButtonBar>
    </ViewContent>
}
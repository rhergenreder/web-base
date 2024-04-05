import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    Box, Button, Checkbox,
    CircularProgress, FormControl, FormControlLabel,
    FormGroup, FormLabel, Grid, IconButton,
    Paper, Select, styled,
    Tab,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableContainer,
    TableRow,
    Tabs, TextField
} from "@mui/material";
import {Link} from "react-router-dom";
import {
    Add,
    Delete,
    Google,
    LibraryBooks,
    Mail,
    RestartAlt,
    Save,
    Send,
    SettingsApplications
} from "@mui/icons-material";
import TIME_ZONES from "shared/time-zones";

const SettingsFormGroup = styled(FormGroup)((props) => ({
    marginBottom: props.theme.spacing(1),
}));

const ButtonBar = styled(Box)((props) => ({
    "& > button": {
        marginRight: props.theme.spacing(1)
    }
}));

export default function SettingsView(props) {

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
      "recaptcha": [
          "recaptcha_enabled",
          "recaptcha_private_key",
          "recaptcha_public_key",
      ],
      "hidden": ["installation_completed", "mail_last_sync"]
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
                        .filter(key => !KNOWN_SETTING_KEYS.hidden.includes(key))
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
                   showDialog(data.msg, L("settings.send_test_email_error"));
               } else {
                   showDialog(L("settings.send_test_email_success"), L("general.success"));
                   setTestMailAddress("");
               }
            });
        }
    }, [api, showDialog, testMailAddress, isSending]);

    if (settings === null) {
        return <CircularProgress />
    }

    const parseBool = (v) => v !== undefined && (v === true || v === 1 || ["true", "1", "yes"].includes(v.toString().toLowerCase()));

    const renderTextInput = (key_name, disabled=false, props={}) => {
        return <SettingsFormGroup key={"form-" + key_name} {...props}>
            <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
            <FormControl>
                <TextField size={"small"} variant={"outlined"}
                           disabled={disabled}
                           value={settings[key_name]}
                           onChange={e => onChangeValue(key_name, e.target.value)} />
            </FormControl>
        </SettingsFormGroup>
    }

    const renderPasswordInput = (key_name, disabled=false, props={}) => {
        return <SettingsFormGroup key={"form-" + key_name} {...props}>
            <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
            <FormControl>
                <TextField size={"small"} variant={"outlined"}
                           type={"password"}
                           disabled={disabled}
                           placeholder={"(" + L("settings.unchanged") + ")"}
                           value={settings[key_name]}
                           onChange={e => onChangeValue(key_name, e.target.value)} />
            </FormControl>
        </SettingsFormGroup>
    }

    const renderNumberInput = (key_name, minValue, maxValue, disabled=false, props={}) => {
        return <SettingsFormGroup key={"form-" + key_name} {...props}>
            <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
            <FormControl>
                <TextField size={"small"} variant={"outlined"}
                           type={"number"}
                           disabled={disabled}
                           inputProps={{min: minValue, max: maxValue}}
                           value={settings[key_name]}
                           onChange={e => onChangeValue(key_name, e.target.value)} />
            </FormControl>
        </SettingsFormGroup>
    }

    const renderCheckBox = (key_name, disabled=false, props={}) => {
        return <SettingsFormGroup key={"form-" + key_name} {...props}>
            <FormControlLabel
                disabled={disabled}
                control={<Checkbox
                    disabled={disabled}
                    checked={parseBool(settings[key_name])}
                    onChange={(e, v) => onChangeValue(key_name, v)} />}
                label={L("settings." + key_name)} />
        </SettingsFormGroup>
    }

    const renderSelection = (key_name, options, disabled=false, props={}) => {
        return <SettingsFormGroup key={"form-" + key_name} {...props}>
            <FormLabel disabled={disabled}>{L("settings." + key_name)}</FormLabel>
            <FormControl>
                <Select native value={settings[key_name]}
                        disabled={disabled}
                        size={"small"} onChange={e => onChangeValue(key_name, e.target.value)}>
                        {options.map(option => <option
                            key={"option-" + option}
                            value={option}>
                                {option}
                        </option>)}
                </Select>
            </FormControl>
        </SettingsFormGroup>
    }

    const renderTab = () => {
        if (selectedTab === "general") {
            return [
                renderTextInput("site_name"),
                renderTextInput("base_url"),
                renderCheckBox("user_registration_enabled"),
                renderTextInput("allowed_extensions"),
                renderSelection("time_zone", TIME_ZONES),
            ];
        } else if (selectedTab === "mail") {
            return [
                renderCheckBox("mail_enabled"),
                renderTextInput("mail_from", !parseBool(settings.mail_enabled)),
                renderTextInput("mail_host", !parseBool(settings.mail_enabled)),
                renderNumberInput("mail_port", 1, 65535, !parseBool(settings.mail_enabled)),
                renderTextInput("mail_username", !parseBool(settings.mail_enabled)),
                renderPasswordInput("mail_password", !parseBool(settings.mail_enabled)),
                renderTextInput("mail_footer", !parseBool(settings.mail_enabled)),
                renderCheckBox("mail_async", !parseBool(settings.mail_enabled)),
                <FormGroup key={"mail-test"}>
                    <FormLabel>{L("settings.send_test_email")}</FormLabel>
                    <FormControl disabled={!parseBool(settings.mail_enabled)}>
                        <Grid container spacing={1}>
                            <Grid item xs={1}>
                                <Button startIcon={isSending ? <CircularProgress size={14} /> : <Send />}
                                        variant={"outlined"} onClick={onSendTestMail}
                                        fullWidth={true}
                                        disabled={!parseBool(settings.mail_enabled) || isSending || !api.hasPermission("mail/test")}>
                                    {isSending ? L("general.sending") + "…" : L("general.send")}
                                </Button>
                            </Grid>
                            <Grid item xs={11}>
                                <TextField disabled={!parseBool(settings.mail_enabled)}
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
        } else if (selectedTab === "recaptcha") {
            return [
                renderCheckBox("recaptcha_enabled"),
                renderTextInput("recaptcha_public_key", !parseBool(settings.recaptcha_enabled)),
                renderPasswordInput("recaptcha_private_key", !parseBool(settings.recaptcha_enabled)),
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

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>{L("settings.title")}</h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">{L("settings.title")}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div className={"content"}>
            <Tabs value={selectedTab} onChange={(e, v) => setSelectedTab(v)} component={Paper}>
                <Tab value={"general"} label={L("settings.general")}
                     icon={<SettingsApplications />} iconPosition={"start"} />
                <Tab value={"mail"} label={L("settings.mail")}
                     icon={<Mail />} iconPosition={"start"} />
                <Tab value={"recaptcha"} label={L("settings.recaptcha")}
                     icon={<Google />} iconPosition={"start"} />
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
                        onClick={() => { setFetchSettings(true); setNewKey(""); }}
                        disabled={isSaving}
                        startIcon={<RestartAlt />}
                        variant={"outlined"} title={L("general.reset")}>
                    {L("general.reset")}
                </Button>
            </ButtonBar>
        </div>
    </>

}
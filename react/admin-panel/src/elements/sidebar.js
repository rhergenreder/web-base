import React, {useCallback, useContext, useEffect, useState} from 'react';
import {useNavigate} from "react-router-dom";
import {LocaleContext} from "shared/locale";
import {
    Box, Divider,
    IconButton, List, ListItem, ListItemButton, ListItemIcon, ListItemText,
    Select, Drawer,
    styled, MenuItem, Menu, ThemeProvider, CssBaseline,
} from "@mui/material";
import { Dropdown } from '@mui/base/Dropdown';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import ChevronLeftIcon from '@mui/icons-material/ChevronLeft';
import {Dns, Groups, People, QueryStats, Security, Settings, Route, ArrowBack, Translate} from "@mui/icons-material";
import useCurrentPath from "shared/hooks/current-path";
import ProfileLink from "./profile-link";

const drawerWidth = 240;

const DrawerHeader = styled('div')(({ theme }) => ({
    display: 'flex',
    alignItems: 'center',
    justifyContent: "flex-start",
    padding: theme.spacing(0, 1),
    ...theme.mixins.toolbar,
    "& > button": {
        display: "flex",
    },
    "& > img": {
        width: 30,
        height: 30,
    },
    "& > span": {
        marginLeft: theme.spacing(2),
        fontSize: "1.5em",
    }
}));

const openedMixin = (theme) => ({
    width: drawerWidth,
    transition: theme.transitions.create('width', {
        easing: theme.transitions.easing.sharp,
        duration: theme.transitions.duration.enteringScreen,
    }),
    overflowX: 'hidden',
});

const closedMixin = (theme) => ({
    transition: theme.transitions.create('width', {
        easing: theme.transitions.easing.sharp,
        duration: theme.transitions.duration.leavingScreen,
    }),
    overflowX: 'hidden',
    width: `calc(${theme.spacing(7)} + 1px)`,
    [theme.breakpoints.up('sm')]: {
        width: `calc(${theme.spacing(8)} + 1px)`,
    },
});

const StyledDrawer = styled(Drawer, { shouldForwardProp: (prop) => prop !== 'open' })(
    ({ theme, open }) => ({
        width: drawerWidth,
        flexShrink: 0,
        whiteSpace: 'nowrap',
        boxSizing: 'border-box',
        ...(open && {
            ...openedMixin(theme),
            '& .MuiDrawer-paper': openedMixin(theme),
        }),
        ...(!open && {
            ...closedMixin(theme),
            '& .MuiDrawer-paper': closedMixin(theme),
        }),
    }),
);

export default function Sidebar(props) {

    const {api, showDialog, hideDialog, theme, info, children, ...other} = props;

    const {translate: L, currentLocale, setLanguageByCode} = useContext(LocaleContext);
    const [languages, setLanguages] = useState(null);
    const [fetchLanguages, setFetchLanguages] = useState(true);
    const [drawerOpen, setDrawerOpen] = useState(window.screen.width >= 1000);
    const [anchorEl, setAnchorEl] = useState(null);
    const navigate = useNavigate();
    const currentPath = useCurrentPath();

    const onLogout = useCallback(() => {
        api.logout().then(obj => {
            if (obj.success) {
                document.location = "/admin";
            } else {
                showDialog("Error logging out: " + obj.msg, "Error logging out");
            }
        });
    }, [api, showDialog]);

    const onSetLanguage = useCallback((code) => {
        setLanguageByCode(api, code).then((res) => {
            if (!res.success) {
                showDialog(res.msg, L("general.error_language_set"));
            }
        });
    }, [api, showDialog]);

    const onFetchLanguages = useCallback((force = false) => {
        if (force || fetchLanguages) {
            setFetchLanguages(false);
            api.getLanguages().then((res) => {
                if (res.success) {
                    setLanguages(res.languages);
                } else {
                    setLanguages({});
                    showDialog(res.msg, L("general.error_language_fetch"));
                }
            });
        }
    }, [api, fetchLanguages, showDialog]);

    useEffect(() => {
        onFetchLanguages();
    }, []);

    const menuItems= {
        "dashboard": {
            "name": "admin.dashboard",
            "icon": <QueryStats />
        },
        "users": {
            "name": "admin.users",
            "icon": <People />,
            "match": /\/admin\/(users|user\/.*)/
        },
        "groups": {
            "name": "admin.groups",
            "icon": <Groups />,
            "match": /\/admin\/(groups|group\/.*)/
        },
        "routes": {
            "name": "admin.page_routes",
            "icon": <Route />,
            "match": /\/admin\/(routes|route\/.*)/
        },
        "settings": {
            "name": "admin.settings",
            "icon": <Settings />
        },
        "permissions": {
            "name": "admin.acl",
            "icon": <Security />
        },
        "logs": {
            "name": "admin.logs",
            "icon": <Dns />
        },
    };

    const NavbarItem = (props) => <ListItem disablePadding sx={{ display: 'block' }}>
        <ListItemButton onClick={props.onClick} selected={props.active} sx={{
                    minHeight: 48,
                    justifyContent: drawerOpen ? 'initial' : 'center',
                    px: 2.5,
                }}>
            <ListItemIcon sx={{
                    minWidth: 0,
                    mr: drawerOpen ? 2 : 'auto',
                    justifyContent: 'center',
                }}>
                {props.icon}
            </ListItemIcon>
            <ListItemText primary={L(props.name)} sx={{ display: drawerOpen ? "block" : "none" }} />
        </ListItemButton>
    </ListItem>

    let li = [];
    for (const [id, menuItem] of Object.entries(menuItems)) {

        let active;
        if (menuItem.hasOwnProperty("match")) {
            active = !!menuItem.match.exec(currentPath);
        } else {
            const match= /^\/admin\/(.*)$/.exec(currentPath);
            active = match?.length >= 2 && match[1] === id;
        }

        li.push(<NavbarItem key={id} {...menuItem} active={active} onClick={() => navigate(`/admin/${id}`)} />);
    }

    li.push(<NavbarItem key={"logout"} name={"general.logout"} icon={<ArrowBack />} onClick={onLogout}/>);

    return <ThemeProvider theme={theme}>
        <CssBaseline />
        <Box sx={{ display: 'flex' }} {...other}>
            <StyledDrawer variant={"permanent"} open={drawerOpen}>
                <DrawerHeader>
                    {drawerOpen && <>
                        <img src={"/img/icons/logo.png"} alt={"Logo"} />
                        <span>WebBase</span>
                    </>}
                    <IconButton sx={{marginLeft: drawerOpen ? "auto" : 0}} onClick={() => setDrawerOpen(!drawerOpen)}>
                        {drawerOpen ? <ChevronLeftIcon/> : <ChevronRightIcon/>}
                    </IconButton>
                </DrawerHeader>
                <Divider/>
                <ListItem sx={{display: 'block'}}>
                    <Box sx={{opacity: drawerOpen ? 1 : 0}}>{L("account.logged_in_as")}:</Box>
                    <ProfileLink text={drawerOpen ? null : ""}
                                 user={api.user} size={30}
                                 sx={{marginTop: 1, gridGap: 16, fontWeight: "bold" }}
                        onClick={() => navigate("/admin/profile")} />
                </ListItem>
                <Divider/>
                <List>
                    {li}
                </List>
                <Divider/>
                <ListItem sx={{display: 'block'}}>
                    { drawerOpen ?
                        <Select native value={currentLocale} size={"small"} fullWidth={true}
                                onChange={e => onSetLanguage(e.target.value)}>
                            {Object.values(languages || {}).map(language =>
                                <option key={language.code} value={language.code}>
                                    {language.name}
                                </option>)
                            }
                        </Select>
                        : <ListItemButton sx={{
                            minHeight: 48,
                            justifyContent: 'center',
                        }}>
                            <Dropdown>
                                <ListItemIcon onClick={e => setAnchorEl(e.currentTarget)} sx={{
                                    minWidth: 0,
                                    mr: 'auto',
                                    justifyContent: 'center',
                                }}>
                                    <Translate />
                                </ListItemIcon>
                                <Menu open={!!anchorEl}
                                      anchorEl={anchorEl}
                                      onClose={() => setAnchorEl(null)}
                                      onClick={() => setAnchorEl(null)}
                                      transformOrigin={{ horizontal: 'left', vertical: 'bottom' }}
                                      anchorOrigin={{ horizontal: 'left', vertical: 'bottom' }}>
                                    {Object.values(languages || {}).map(language =>
                                        <MenuItem key={language.code} onClick={() => onSetLanguage(language.code)}>
                                            {language.name}
                                        </MenuItem>)
                                    }
                                </Menu>
                            </Dropdown>
                        </ListItemButton>
                    }
                </ListItem>
            </StyledDrawer>
            <Box component="main" sx={{flexGrow: 1, p: 1}}>
                {children}
            </Box>
        </Box>
    </ThemeProvider>
}

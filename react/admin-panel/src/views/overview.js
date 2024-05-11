import * as React from "react";
import {Link} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {humanReadableSize} from "shared/util";
import {sprintf} from "sprintf-js";
import {
    ArrowCircleRight,
    BugReport,
    CheckCircle,
    Groups,
    HighlightOff,
    LibraryBooks,
    People
} from "@mui/icons-material";
import {
    Box,
    CircularProgress,
    Divider,
    Grid,
    Paper,
    styled,
    Table,
    TableBody,
    TableCell,
    TableRow
} from "@mui/material";
import ViewContent from "../elements/view-content";
import {Alert} from "@mui/lab";

const StyledStatBox = styled(Alert)((props) => ({
    position: "relative",
    padding: 0,
    "& > div": {
        padding: 0,
        width: "100%",
        "& a": {
            color: "white",
        },
        "& div:nth-of-type(1)": {
            padding: props.theme.spacing(2),
            "& span": {
                fontSize: "2.5em",
            },
            "& p": {
                fontSize: "1em",
            }
        },
        "& div:nth-of-type(2) > svg": {
            position: "absolute",
            top: props.theme.spacing(1),
            right: props.theme.spacing(1),
            opacity: 0.6,
            fontSize: "5em"
        },
        "& div:nth-of-type(3)": {
            backdropFilter: "brightness(70%)",
            padding: props.theme.spacing(0.5),
            "& a": {
                display: "grid",
                gridTemplateColumns: "auto 30px",
                alignItems: "center",
                justifyContent: "end",
                textDecoration: "none",
                "& svg": {
                    textAlign: "center",
                    justifySelf: "center"
                }
            }
        }
    },
}));

const StatBox = (props) => <StyledStatBox variant={"filled"} icon={false}
                                          severity={props.color}>
        <Box>
            {!isNaN(props.count) ?
                <>
                    <span>{props.count}</span>
                    <p>{props.text}</p>
                </> : <CircularProgress variant={"determinate"} />
            }
        </Box>
        <Box>{props.icon}</Box>
        <Box>
            <Link to={props.link}>
                <span>{props.L("admin.more_info")}</span>
                <ArrowCircleRight />
            </Link>
        </Box>
    </StyledStatBox>

const StatusLine = (props) => {
    const {enabled, text, ...other} = props;
    if (enabled) {
        return <Box display="grid" gridTemplateColumns={"30px auto"} alignItems={"center"}>
            <CheckCircle color={"primary"} title={text} /> {text}
        </Box>
    } else {
        return <Box display="grid" gridTemplateColumns={"30px auto"} alignItems={"center"}>
            <HighlightOff color={"error"} title={text} /> {text}
        </Box>
    }
}

export default function Overview(props) {

    const [fetchStats, setFetchStats] = useState(true);
    const [stats, setStats] = useState(null);
    const {translate: L, currentLocale, requestModules} = useContext(LocaleContext);


    useEffect(() => {
        requestModules(props.api, ["general", "admin", "settings"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchStats = useCallback((force = false) => {
        if (force || fetchStats) {
            setFetchStats(false);
            props.api.getStats().then((res) => {
                if (res.success) {
                    setStats(res.data);
                } else {
                    props.showDialog(res.msg, L("admin.fetch_stats_error"));
                }
            });
        }
    }, [fetchStats]);

    useEffect(() => {
        onFetchStats();
    }, []);

    let loadAvg = stats ? stats.server.loadAverage : null;
    if (Array.isArray(loadAvg)) {
        loadAvg = loadAvg.map(v => sprintf("%.1f", v)).join(", ");
    }

    return <ViewContent title={L("admin.dashboard")} path={[
        <Link key={"home"} to={"/admin/dashboard"}>Home</Link>
    ]}>
        <Grid container spacing={2}>
            <Grid item xs={6} lg={3}>
                <StatBox color={"info"} count={stats?.userCount}
                         text={L("admin.users_registered")}
                         icon={<People/>}
                         link={"/admin/users"} L={L}/>
            </Grid>
            <Grid item xs={6} lg={3}>
                <StatBox color={"success"} count={stats?.groupCount}
                         text={L("admin.available_groups")}
                         icon={<Groups/>}
                         link={"/admin/groups"} L={L}/>
            </Grid>
            <Grid item xs={6} lg={3}>
                <StatBox color={"warning"} count={stats?.pageCount}
                         text={L("admin.routes_defined")}
                         icon={<LibraryBooks/>}
                         link={"/admin/routes"} L={L}/>
            </Grid>
            <Grid item xs={6} lg={3}>
                <StatBox color={"error"} count={stats?.errorCount}
                         text={L("admin.error_count")}
                         icon={<BugReport />}
                         link={"/admin/logs"} L={L}/>
            </Grid>
        </Grid>
        <Box m={2} p={2} component={Paper}>
            <h4>Server Stats</h4>
            <Divider />
            {stats === null ? <CircularProgress/> :
                <Table>
                    <TableBody>
                        <TableRow>
                            <TableCell>Web-Base Version</TableCell>
                            <TableCell>{stats.server.version}</TableCell>
                        </TableRow>
                        <TableRow>
                            <TableCell>Server</TableCell>
                            <TableCell>{stats.server.server ?? "Unknown"}</TableCell>
                        </TableRow>
                        <TableRow>
                            <TableCell>Load Average</TableCell>
                            <TableCell>{loadAvg}</TableCell>
                        </TableRow>
                        <TableRow>
                            <TableCell>Memory Usage</TableCell>
                            <TableCell>{humanReadableSize(stats.server.memoryUsage)}</TableCell>
                        </TableRow>
                        <TableRow>
                            <TableCell>Database</TableCell>
                            <TableCell>{stats.server.database}</TableCell>
                        </TableRow>
                        <TableRow>
                            <TableCell>Captcha</TableCell>
                            <TableCell>
                                <StatusLine enabled={!!stats.server.captcha}
                                            text={L("settings." + (stats.server.captcha ? stats.server.captcha.name : "disabled"))}
                                />
                            </TableCell>
                        </TableRow>
                        <TableRow>
                            <TableCell>Mail</TableCell>
                            <TableCell>
                                <StatusLine enabled={!!stats.server.mail}
                                            text={L("settings." + (stats.server.mail ? "enabled" : "disabled"))}
                                />
                            </TableCell>
                        </TableRow>
                        <TableRow>
                            <TableCell>Rate-Limiting</TableCell>
                            <TableCell>
                                <StatusLine enabled={!!stats.server.rateLimiting}
                                            text={L("settings." + (stats.server.rateLimiting ? "enabled" : "disabled"))}
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            }
        </Box>
    </ViewContent>
}

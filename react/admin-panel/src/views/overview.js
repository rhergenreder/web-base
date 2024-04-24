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
import {Box, CircularProgress, Paper, Table, TableBody, TableCell, TableRow} from "@mui/material";

const StatBox = (props) => <div className={"col-lg-3 col-6"}>
    <div className={"small-box bg-" + props.color}>
        <div className={"inner"}>
            {!isNaN(props.count) ?
                <>
                    <h3>{props.count}</h3>
                    <p>{props.text}</p>
                </> : <CircularProgress variant={"determinate"} />
            }
        </div>
        <div className={"icon"}>
            {props.icon}
        </div>
        <Link to={props.link} className={"small-box-footer text-right p-1"}>
            More info <ArrowCircleRight />
        </Link>
    </div>
</div>

const StatusLine = (props) => {
    const {enabled, text, ...other} = props;
    if (enabled) {
        return <Box display="grid" gridTemplateColumns={"30px auto"}>
            <CheckCircle color={"primary"} title={text} /> {text}
        </Box>
    } else {
        return <Box display="grid" gridTemplateColumns={"30px auto"}>
            <HighlightOff color={"secondary"} title={text} /> {text}
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

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>{L("admin.dashboard")}</h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">{L("admin.dashboard")}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section className={"content"}>
            <div className={"container-fluid"}>
                <div className={"row"}>
                    <StatBox color={"info"} count={stats?.userCount}
                             text={L("admin.users_registered")}
                             icon={<People />}
                             link={"/admin/users"} />
                    <StatBox color={"success"} count={stats?.groupCount}
                             text={L("admin.available_groups")}
                             icon={<Groups />}
                             link={"/admin/groups"} />
                    <StatBox color={"warning"} count={stats?.pageCount}
                             text={L("admin.routes_defined")}
                             icon={<LibraryBooks />}
                             link={"/admin/routes"} />
                    <StatBox color={"danger"} count={stats?.errorCount}
                             text={L("admin.error_count")}
                             icon={<BugReport />}
                             link={"/admin/logs"} />
                </div>
            </div>
            <Box m={2} p={2} component={Paper}>
                <h4>Server Stats</h4><hr />
                {stats === null ? <CircularProgress /> :
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
        </section>
    </>
}

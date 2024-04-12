import * as React from "react";
import {Link} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {ArrowCircleRight, BugReport, Groups, LibraryBooks, People} from "@mui/icons-material";
import {CircularProgress} from "@mui/material";

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

export default function Overview(props) {

    const [fetchStats, setFetchStats] = useState(true);
    const [stats, setStats] = useState(null);
    const {translate: L, currentLocale, requestModules} = useContext(LocaleContext);


    useEffect(() => {
        requestModules(props.api, ["general", "admin"], currentLocale).then(data => {
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

    /*
    let loadAvg = this.state.server.load_avg;
    if (Array.isArray(this.state.server.load_avg)) {
        loadAvg = this.state.server.load_avg.join(" ");
    }
     */

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
        </section>
    </>
}

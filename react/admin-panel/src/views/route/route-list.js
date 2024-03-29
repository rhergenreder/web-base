import {Link, useNavigate} from "react-router-dom";
import {
    Paper,
    styled,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    IconButton, Button, Checkbox
} from "@material-ui/core";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Add, Cached, Delete, Edit, Refresh} from "@material-ui/icons";
import Dialog from "shared/elements/dialog";

const RouteTableRow = styled(TableRow)((props) => ({
    "& td": {
        fontFamily: "monospace"
    }
}));


export default function RouteListView(props) {

    // meta
    const api = props.api;
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const navigate = useNavigate();

    // data
    const [fetchRoutes, setFetchRoutes] = useState(true);
    const [routes, setRoutes] = useState({});

    // ui
    const [dialogData, setDialogData] = useState({show: false});
    const [isGeneratingCache, setGeneratingCache] = useState(false);

    const onFetchRoutes = useCallback((force = false) => {
        if (force || fetchRoutes) {
            setFetchRoutes(false);
            props.api.fetchRoutes().then(res => {
                if (!res.success) {
                    props.showDialog(res.msg, "Error fetching routes");
                    navigate("/admin/dashboard");
                } else {
                    setRoutes(res.routes);
                }
            });
        }
    }, [fetchRoutes]);

    useEffect(() => {
        onFetchRoutes();
    }, []);

    useEffect(() => {
        requestModules(props.api, ["general"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onToggleRoute = useCallback((id, active) => {
        if (active) {
            props.api.enableRoute(id).then(data => {
                if (!data.success) {
                    props.showDialog(data.msg, L("Error enabling route"));
                } else {
                    setRoutes({...routes, [id]: { ...routes[id], active: true }});
                }
            });
        } else {
            props.api.disableRoute(id).then(data => {
                if (!data.success) {
                    props.showDialog(data.msg, L("Error enabling route"));
                } else {
                    setRoutes({...routes, [id]: { ...routes[id], active: false }});
                }
            });
        }
    }, [routes]);

    const onDeleteRoute = useCallback(id => {
        props.api.deleteRoute(id).then(data => {
            if (!data.success) {
                props.showDialog(data.msg, L("Error removing route"));
            } else {
                let newRoutes = { ...routes };
                delete newRoutes[id];
                setRoutes(newRoutes);
            }
        })
    }, [routes]);

    const onRegenerateCache = useCallback(() => {
        if (!isGeneratingCache) {
            setGeneratingCache(true);
            props.api.regenerateRouterCache().then(data => {
                if (!data.success) {
                    props.showDialog(data.msg, L("Error regenerating router cache"));
                    setGeneratingCache(false);
                } else {
                    setDialogData({
                        open: true,
                        title: L("general.success"),
                        message: L("Router cache successfully regenerated"),
                        onClose: () => setGeneratingCache(false)
                    })
                }
            });
        }
    }, [isGeneratingCache]);

    const BoolCell = (props) => props.checked ? L("general.yes") : L("general.no")

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>Routes</h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">Routes</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div className={"row"}>
            <div className={"col-6"} />
                <div className={"col-6 text-right"}>
                <div className={"form-group"}>
                    <Button variant={"outlined"} color={"primary"} className={"m-1"} startIcon={<Refresh />} onClick={() => onFetchRoutes(true)}>
                        {L("general.reload")}
                    </Button>
                    <Button variant={"outlined"} className={"m-1"} startIcon={<Add />}
                            disabled={!props.api.hasPermission("routes/add")}
                            onClick={() => navigate("/admin/routes/new")} >
                        {L("general.add")}
                    </Button>
                    <Button variant={"outlined"} className={"m-1"} startIcon={<Cached />}
                            disabled={!props.api.hasPermission("routes/generateCache") || isGeneratingCache}
                            onClick={onRegenerateCache} >
                        {isGeneratingCache ? L("regenerating_cache") + "…" : L("regenerate_cache")}
                    </Button>
                </div>
            </div>
        </div>
        <TableContainer component={Paper} style={{overflowX: "initial"}}>
            <Table stickyHeader size={"small"} className={"table-striped"}>
                <TableHead>
                    <TableRow>
                        <TableCell>{L("general.id")}</TableCell>
                        <TableCell>{L("Route")}</TableCell>
                        <TableCell>{L("Type")}</TableCell>
                        <TableCell>{L("Target")}</TableCell>
                        <TableCell>{L("Extra")}</TableCell>
                        <TableCell align={"center"}>{L("Active")}</TableCell>
                        <TableCell align={"center"}>{L("Exact")}</TableCell>
                        <TableCell align={"center"}>{L("general.controls")}</TableCell>
                    </TableRow>
                </TableHead>
                <TableBody>
                    {Object.entries(routes).map(([id, route]) =>
                        <RouteTableRow key={"route-" + id}>
                            <TableCell>{route.id}</TableCell>
                            <TableCell>{route.pattern}</TableCell>
                            <TableCell>{route.type}</TableCell>
                            <TableCell>{route.target}</TableCell>
                            <TableCell>{route.extra}</TableCell>
                            <TableCell align={"center"}>
                                <Checkbox checked={route.active}
                                          size={"small"}
                                          disabled={!api.hasPermission(route.active ? "routes/disable" : "routes/enable")}
                                          onChange={(e) => onToggleRoute(route.id, e.target.checked)} />
                            </TableCell>
                            <TableCell align={"center"}><BoolCell checked={route.exact} /></TableCell>
                            <TableCell align={"center"}>
                                <IconButton size={"small"} title={L("general.edit")}
                                            disabled={!api.hasPermission("routes/add")}
                                            color={"primary"}
                                            onClick={() => navigate("/admin/routes/" + id)}>
                                    <Edit />
                                </IconButton>
                                <IconButton size={"small"} title={L("general.delete")}
                                            disabled={!api.hasPermission("routes/remove")}
                                            color={"secondary"}
                                            onClick={() => setDialogData({
                                                open: true,
                                                title: L("Delete Route"),
                                                message: L("Do you really want to delete the following route?"),
                                                inputs: [
                                                    { type: "text", value: route.pattern, disabled: true}
                                                ],
                                                options: [L("general.ok"), L("general.cancel")],
                                                onOption: btn => btn === 0 && onDeleteRoute(route.id)
                                            })}>
                                    <Delete />
                                </IconButton>
                            </TableCell>
                        </RouteTableRow>
                    )}
                </TableBody>
            </Table>
        </TableContainer>
        <Dialog show={dialogData.open}
                onClose={() => { setDialogData({open: false}); dialogData.onClose && dialogData.onClose() }}
                title={dialogData.title}
                message={dialogData.message}
                onOption={dialogData.onOption}
                inputs={dialogData.inputs}
                options={[L("general.ok"), L("general.cancel")]} />
    </>
}
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
    Button,
    IconButton, Checkbox, Box
} from "@mui/material";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Add, Cached, Delete, Edit, Refresh} from "@mui/icons-material";
import Dialog from "shared/elements/dialog";
import ViewContent from "../../elements/view-content";
import ButtonBar from "../../elements/button-bar";
import TableBodyStriped from "shared/elements/table-body-striped";

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
                    props.showDialog(res.msg, L("routes.fetch_routes_error"));
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
        requestModules(props.api, ["general", "routes"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onToggleRoute = useCallback((id, active) => {
        if (active) {
            props.api.enableRoute(id).then(data => {
                if (!data.success) {
                    props.showDialog(data.msg, L("routes.enable_route_error"));
                } else {
                    setRoutes({...routes, [id]: { ...routes[id], active: true }});
                }
            });
        } else {
            props.api.disableRoute(id).then(data => {
                if (!data.success) {
                    props.showDialog(data.msg, L("routes.disable_route_error"));
                } else {
                    setRoutes({...routes, [id]: { ...routes[id], active: false }});
                }
            });
        }
    }, [routes]);

    const onDeleteRoute = useCallback(id => {
        props.api.deleteRoute(id).then(data => {
            if (!data.success) {
                props.showDialog(data.msg, L("routes.remove_route_error"));
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
                    props.showDialog(data.msg, L("routes.regenerate_router_cache_error"));
                    setGeneratingCache(false);
                } else {
                    setDialogData({
                        open: true,
                        title: L("general.success"),
                        message: L("routes.regenerate_router_cache_success"),
                        onClose: () => setGeneratingCache(false)
                    })
                }
            });
        }
    }, [isGeneratingCache]);

    const BoolCell = (props) => props.checked ? L("general.yes") : L("general.no")

    return <>
        <ViewContent title={L("routes.title")} path={[
            <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
            <span key={"routes"}>{L("routes.title")}</span>
        ]}>
            <ButtonBar mb={1}>
                <Button variant={"outlined"} color={"primary"} size={"small"}
                        startIcon={<Refresh />} onClick={() => onFetchRoutes(true)}>
                    {L("general.reload")}
                </Button>
                <Button variant={"outlined"} color={"success"} startIcon={<Add />} size={"small"}
                        disabled={!props.api.hasPermission("routes/add")}
                        onClick={() => navigate("/admin/routes/new")} >
                    {L("general.add")}
                </Button>
                <Button variant={"outlined"} color={"info"} startIcon={<Cached />} size={"small"}
                        disabled={!props.api.hasPermission("routes/generateCache") || isGeneratingCache}
                        onClick={onRegenerateCache} >
                    {isGeneratingCache ? L("routes.regenerating_cache") + "…" : L("routes.regenerate_cache")}
                </Button>
            </ButtonBar>
            <TableContainer component={Paper} sx={{overflowX: "initial"}}>
                <Table stickyHeader size={"small"}>
                    <TableHead>
                        <TableRow>
                            <TableCell>{L("general.id")}</TableCell>
                            <TableCell>{L("routes.route")}</TableCell>
                            <TableCell>{L("routes.type")}</TableCell>
                            <TableCell>{L("routes.target")}</TableCell>
                            <TableCell>{L("routes.extra")}</TableCell>
                            <TableCell align={"center"}>{L("routes.active")}</TableCell>
                            <TableCell align={"center"}>{L("routes.exact")}</TableCell>
                            <TableCell align={"center"}>{L("general.controls")}</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBodyStriped>
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
                                                color={"error"}
                                                onClick={() => setDialogData({
                                                    open: true,
                                                    title: L("routes.delete_route_title"),
                                                    message: L("routes.delete_route_text"),
                                                    inputs: [
                                                        { type: "text", name: "pattern", value: route.pattern, disabled: true}
                                                    ],
                                                    options: [L("general.cancel"), L("general.ok")],
                                                    onOption: btn => btn === 1 ? onDeleteRoute(route.id) : true
                                                })}>
                                        <Delete />
                                    </IconButton>
                                </TableCell>
                            </RouteTableRow>
                        )}
                    </TableBodyStriped>
                </Table>
            </TableContainer>
        </ViewContent>
        <Dialog show={dialogData.open}
                onClose={() => {
                    setDialogData({open: false});
                    dialogData.onClose && dialogData.onClose()
                }}
                title={dialogData.title}
                message={dialogData.message}
                onOption={dialogData.onOption}
                inputs={dialogData.inputs}
                options={[L("general.cancel"), L("general.ok")]} />
    </>
}
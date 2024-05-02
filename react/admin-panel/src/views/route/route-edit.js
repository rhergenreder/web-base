import {Link, useNavigate, useParams} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    Box,
    Button,
    TextField,
    CircularProgress, styled,
} from "@mui/material";
import * as React from "react";
import RouteForm from "./route-form";
import {KeyboardArrowLeft, Save} from "@mui/icons-material";
import ButtonBar from "../../elements/button-bar";
import ViewContent from "../../elements/view-content";

const MonoSpaceTextField = styled(TextField)((props) => ({
    "& input": {
        fontFamily: "monospace"
    }
}));

export default function RouteEditView(props) {

    const {api, showDialog} = props;
    const {routeId} = useParams();
    const navigate = useNavigate();
    const isNewRoute = routeId === "new";
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);

    // data
    const [routeTest, setRouteTest] = useState("");
    const [fetchRoute, setFetchRoute] = useState(!isNewRoute);
    const [route, setRoute] = useState(isNewRoute ? {
        pattern: "",
        type: "",
        target: "",
        extra: "",
        exact: true,
        active: true
    } : null);

    // ui
    const [routeTestResult, setRouteTestResult] = useState(false);
    const [isSaving, setSaving] = useState(false);

    useEffect(() => {
        requestModules(props.api, ["general", "routes"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    useEffect(() => {
        if (routeTest?.trim()) {
            props.api.testRoute(route.pattern, routeTest, route.exact).then(data => {
               if (!data.success) {
                   props.showDialog("Error testing route: " + data.msg);
               } else {
                   setRouteTestResult(data.match);
               }
            });
        } else {
            setRouteTestResult(false);
        }
    }, [routeTest]);

    const onFetchRoute = useCallback((force = false) => {
        if (!isNewRoute && (force || fetchRoute)) {
            setFetchRoute(false);
            api.getRoute(routeId).then((res) => {
                if (!res.success) {
                    showDialog(res.msg, L("routes.fetch_route_error"));
                    navigate("/admin/routes");
                } else {
                    setRoute(res.route);
                }
            });
        }
    }, [api, showDialog, fetchRoute, isNewRoute, routeId, route]);

    const onSave = useCallback(() => {
        if (!isSaving) {
            setSaving(true);
            let extra = ["dynamic", "static"].includes(route.type) ? route.extra : "";
            let args = [route.pattern, route.type, route.target, extra, route.exact, route.active];
            if (isNewRoute) {
                api.addRoute(...args).then(res => {
                    setSaving(false);
                    if (res.success) {
                        navigate("/admin/routes/" + res.routeId);
                    } else {
                        showDialog(res.msg, L("routes.save_route_error"));
                    }
                });
            } else {
                args = [routeId, ...args];
                api.updateRoute(...args).then(res => {
                    setSaving(false);
                    if (!res.success) {
                        showDialog(res.msg, L("routes.save_route_error"));
                    }
                });
            }
        }
    }, [api, route, isSaving, isNewRoute, routeId]);

    useEffect(() => {
        if (!isNewRoute) {
            onFetchRoute(true);
        }
    }, []);

    if (route === null) {
        return <CircularProgress/>
    }

    return <ViewContent title={L(isNewRoute ? "routes.create_route_title" : "routes.edit_route_title")}
        path={[
            <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
            <Link key={"routes"} to={"/admin/routes"}>{L("routes.title")}</Link>,
            <span key={"action"}>{isNewRoute ? L("general.new") : L("general.edit")}</span>,
        ]}>
            <RouteForm route={route} setRoute={setRoute} />
            <ButtonBar mt={2}>
                <Button startIcon={<KeyboardArrowLeft />}
                        variant={"outlined"} color={"error"}
                        onClick={() => navigate("/admin/routes")}>
                    {L("general.cancel")}
                </Button>
                <Button startIcon={isSaving ? <CircularProgress size={14} /> : <Save />}
                        color={"primary"}
                        variant={"outlined"}
                        disabled={isSaving}
                        onClick={onSave}>
                    {isSaving ? L("general.saving") + "…" : L("general.save")}
                </Button>
            </ButtonBar>
            <Box mt={3}>
                <h5>{L("routes.validate_route")}</h5>
                <MonoSpaceTextField value={routeTest} onChange={e => setRouteTest(e.target.value)}
                                    variant={"outlined"} size={"small"} fullWidth={true}
                                    placeholder={L("routes.validate_route_placeholder") + "…"} />
                <pre>
                    Match: {JSON.stringify(routeTestResult)}
                </pre>
            </Box>
    </ViewContent>
}
import {Link, useNavigate, useParams} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    Box,
    Button,
    CircularProgress, styled,
} from "@material-ui/core";
import * as React from "react";
import RouteForm from "./route-form";
import {KeyboardArrowLeft, Save} from "@material-ui/icons";
import {TextField} from "@mui/material";

const ButtonBar = styled(Box)((props) => ({
    "& > button": {
        marginRight: props.theme.spacing(1)
    }
}));

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
        requestModules(props.api, ["general"], currentLocale).then(data => {
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
                    showDialog(res.msg, L("Error fetching route"));
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
            let args = [route.pattern, route.type, route.target, route.extra, route.exact, route.active];
            if (isNewRoute) {
                api.addRoute(...args).then(res => {
                    setSaving(false);
                    if (res.success) {
                        navigate("/admin/routes/" + res.routeId);
                    } else {
                        showDialog(res.msg, L("Error saving route"));
                    }
                });
            } else {
                args = [routeId, ...args];
                api.updateRoute(...args).then(res => {
                    setSaving(false);
                    if (!res.success) {
                        showDialog(res.msg, L("Error saving route"));
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

    return <div className={"content-header"}>
        <div className={"container-fluid"}>
            <ol className={"breadcrumb"}>
                <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                <li className="breadcrumb-item active"><Link to={"/admin/routes"}>Routes</Link></li>
                <li className="breadcrumb-item active">{isNewRoute ? "New" : "Edit"}</li>
            </ol>
        </div>
        <div className={"content"}>
            <div className={"container-fluid"}>
                <h3>{L(isNewRoute ? "Create new Route" : "Edit Route")}</h3>
                <div className={"col-sm-12 col-lg-6"}>
                </div>
            </div>
        </div>
        <RouteForm route={route} setRoute={setRoute} />
        <ButtonBar mt={2}>
            <Button startIcon={<KeyboardArrowLeft />}
                    variant={"outlined"}
                    onClick={() => navigate("/admin/routes")}>
                {L("general.cancel")}
            </Button>
            <Button startIcon={<Save />} color={"primary"}
                    variant={"outlined"} disabled={isSaving}
                    onClick={onSave}>
                {isSaving ? L("general.saving") + "…" : L("general.save")}
            </Button>
        </ButtonBar>
        <Box mt={3}>
            <h5>{L("Validate Route")}</h5>
            <MonoSpaceTextField value={routeTest} onChange={e => setRouteTest(e.target.value)}
                variant={"outlined"} size={"small"} fullWidth={true}
                placeholder={L("Enter a path to test the route…")} />
            <pre>
                Match: {JSON.stringify(routeTestResult)}
            </pre>
        </Box>
    </div>
}
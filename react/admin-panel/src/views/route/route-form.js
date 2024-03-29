import {Box, Checkbox, FormControl, FormControlLabel, FormGroup, Select, styled, TextField} from "@material-ui/core";
import * as React from "react";
import {useCallback, useContext} from "react";
import {LocaleContext} from "shared/locale";

const RouteFormControl = styled(FormControl)((props) => ({
    "& > label": {
        marginTop: 5
    },
    "& input, & textarea": {
        fontFamily: "monospace",
    }
}));

export default function RouteForm(props) {

    const {route, setRoute} = props;
    const {translate: L} = useContext(LocaleContext);

    const onChangeRouteType = useCallback((type) => {
        let newRoute = {...route, type: type };
        if (newRoute.type === "dynamic" && !newRoute.extra) {
            newRoute.extra = "[]";
        } else if (newRoute.type === "static" && !newRoute.extra) {
            newRoute.extra = 200;
        }

        setRoute(newRoute);
    }, [route]);

    const elements = [
        <RouteFormControl key={"form-control-pattern"} fullWidth={true}>
            <label htmlFor={"route-pattern"}>{L("Pattern")}</label>
            <TextField id={"route-pattern"} variant={"outlined"} size={"small"}
                       value={route.pattern}
                       onChange={e => setRoute({...route, pattern: e.target.value})} />
        </RouteFormControl>,
        <FormGroup key={"form-control-exact"}>
            <FormControlLabel label={L("Exact")} control={<Checkbox
                checked={route.exact}
                onChange={e => setRoute({...route, exact: e.target.checked})} />} />
        </FormGroup>,
        <FormGroup key={"form-control-active"}>
            <FormControlLabel label={L("Active")} control={<Checkbox
                checked={route.active}
                onChange={e => setRoute({...route, active: e.target.checked})} />} />
        </FormGroup>,
        <RouteFormControl key={"form-control-type"} fullWidth={true} size={"small"}>
            <label htmlFor={"route-type"}>{L("Type")}</label>
            <Select value={route.type} variant={"outlined"} size={"small"} labelId={"route-type"}
                    onChange={e => onChangeRouteType(e.target.value)}>
                <option value={""}>Select…</option>
                <option value={"dynamic"}>Dynamic</option>
                <option value={"static"}>Static</option>
                <option value={"redirect_permanently"}>Redirect Permanently</option>
                <option value={"redirect_temporary"}>Redirect Temporary</option>
            </Select>
        </RouteFormControl>,
    ];

    if (route.type) {
        elements.push(
            <RouteFormControl key={"form-control-target"} fullWidth={true}>
                <label htmlFor={"route-target"}>{L("Target")}</label>
                <TextField id={"route-target"} variant={"outlined"} size={"small"}
                           value={route.target}
                           onChange={e => setRoute({...route, target: e.target.value})}/>
            </RouteFormControl>
        );

        if (route.type === "dynamic") {
            let extraArgs;
            try {
                extraArgs = JSON.parse(route.extra)
            } catch (e) {
                extraArgs = null
            }
            elements.push(
                <RouteFormControl key={"form-control-extra"} fullWidth={true}>
                    <label htmlFor={"route-extra"}>{L("Arguments")}</label>
                    <textarea id={"route-extra"}
                              value={route.extra}
                              onChange={e => setRoute({...route, extra: e.target.value})}/>
                    <i>{
                        extraArgs === null ?
                            "Invalid JSON-string" :
                            (typeof extraArgs !== "object" ?
                                "JSON must be Array or Object" : "JSON ok!")
                    }</i>
                </RouteFormControl>
            );
        } else if (route.type === "static") {
            elements.push(
                <RouteFormControl key={"form-control-extra"} fullWidth={true}>
                    <label htmlFor={"route-extra"}>{L("Status Code")}</label>
                    <TextField id={"route-extra"} variant={"outlined"} size={"small"}
                               type={"number"} value={route.extra}
                               onChange={e => setRoute({...route, extra: parseInt(e.target.value) || 200})} />
                </RouteFormControl>
            );
        }
    }

    return elements;
}
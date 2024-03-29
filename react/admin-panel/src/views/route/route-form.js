import {Box, Checkbox, FormControl, FormControlLabel, Select, styled, TextField} from "@material-ui/core";
import * as React from "react";
import {useCallback, useContext, useEffect, useRef} from "react";
import {LocaleContext} from "shared/locale";
import {CheckCircle, ErrorRounded} from "@material-ui/icons";

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
    const extraRef = useRef();

    const onChangeRouteType = useCallback((type) => {
        let newRoute = {...route, type: type };
        if (newRoute.type === "dynamic" && !newRoute.extra) {
            newRoute.extra = "[]";
        } else if (newRoute.type === "static" && !newRoute.extra) {
            newRoute.extra = 200;
        }

        setRoute(newRoute);
    }, [route]);

    useEffect(() => {
        if (extraRef.current) {
            const scrollHeight = extraRef.current.scrollHeight + 5;
            extraRef.current.style.height = scrollHeight + "px";
        }
    }, [extraRef?.current, route.extra]);

    const elements = [
        <RouteFormControl key={"form-control-pattern"} fullWidth={true}>
            <label htmlFor={"route-pattern"}>{L("routes.pattern")}</label>
            <TextField id={"route-pattern"} variant={"outlined"} size={"small"}
                       value={route.pattern}
                       onChange={e => setRoute({...route, pattern: e.target.value})} />
        </RouteFormControl>,
        <RouteFormControl key={"form-control-exact"}>
            <FormControlLabel label={L("routes.exact")} control={<Checkbox
                checked={route.exact}
                onChange={e => setRoute({...route, exact: e.target.checked})} />} />
        </RouteFormControl>,
        <RouteFormControl key={"form-control-active"}>
            <FormControlLabel label={L("routes.active")} control={<Checkbox
                checked={route.active}
                onChange={e => setRoute({...route, active: e.target.checked})} />} />
        </RouteFormControl>,
        <RouteFormControl key={"form-control-type"} fullWidth={true} size={"small"}>
            <label htmlFor={"route-type"}>{L("routes.type")}</label>
            <Select value={route.type} variant={"outlined"} size={"small"} labelId={"route-type"}
                    onChange={e => onChangeRouteType(e.target.value)} native>
                <option value={""}>{L("general.select")}…</option>
                <option value={"dynamic"}>{L("routes.type_dynamic")}</option>
                <option value={"static"}>{L("routes.type_static")}</option>
                <option value={"redirect_permanently"}>{L("routes.type_redirect_permanently")}</option>
                <option value={"redirect_temporary"}>{L("routes.type_redirect_temporary")}</option>
            </Select>
        </RouteFormControl>,
    ];

    const minifyJson = (value) => {
        try {
            return JSON.stringify(JSON.parse(value));
        } catch (e) {
            return value;
        }
    }

    if (route.type) {
        elements.push(
            <RouteFormControl key={"form-control-target"} fullWidth={true}>
                <label htmlFor={"route-target"}>{L("routes.target")}</label>
                <TextField id={"route-target"} variant={"outlined"} size={"small"}
                           value={route.target}
                           onChange={e => setRoute({...route, target: e.target.value})}/>
            </RouteFormControl>
        );

        if (route.type === "dynamic") {
            let extraArgs, type, isValid = false;
            try {
                extraArgs = JSON.parse(route.extra);
                type = typeof extraArgs;
                extraArgs = JSON.stringify(extraArgs, null, 2);
                isValid = type === "object";
            } catch (e) {
                extraArgs = null
            }
            elements.push(
                <RouteFormControl key={"form-control-extra"} fullWidth={true}>
                    <label htmlFor={"route-extra"}>{L("routes.arguments")}</label>
                    <textarea id={"route-extra"}
                              ref={extraRef} style={!isValid ? {borderColor: "red"} : {}}
                              value={extraArgs ?? route.extra}
                              onChange={e => setRoute({...route, extra: minifyJson(e.target.value)})}/>
                    <Box mt={1} fontStyle={"italic"} display={"grid"} gridTemplateColumns={"30px auto"}>{
                        extraArgs === null ?
                            <><ErrorRounded color={"secondary"}/><span>{L("routes.json_err")}</span></> :
                                (type !== "object" ?
                                    <><ErrorRounded color={"secondary"}/><span>{L("routes.json_not_object")}</span></> :
                                    <><CheckCircle color={"primary"} /><span>{L("routes.json_ok")}</span></>)
                    }</Box>
                </RouteFormControl>
            );
        } else if (route.type === "static") {
            elements.push(
                <RouteFormControl key={"form-control-extra"} fullWidth={true}>
                    <label htmlFor={"route-extra"}>{L("routes.status_code")}</label>
                    <TextField id={"route-extra"} variant={"outlined"} size={"small"}
                               type={"number"} value={route.extra}
                               onChange={e => setRoute({...route, extra: parseInt(e.target.value) || 200})} />
                </RouteFormControl>
            );
        }
    }

    return elements;
}
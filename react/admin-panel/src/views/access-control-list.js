import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Link, useNavigate} from "react-router-dom";
import {
    Button,
    Checkbox,
    TextField,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    IconButton, styled, FormGroup, FormLabel, Box, Grid
} from "@mui/material";
import {Add, Delete, Edit, Refresh} from "@mui/icons-material";
import {USER_GROUP_ADMIN} from "shared/constants";
import Dialog from "shared/elements/dialog";
import ViewContent from "../elements/view-content";
import ButtonBar from "../elements/button-bar";
import TableBodyStriped from "shared/elements/table-body-striped";

const BorderedColumn = styled(TableCell)({
    borderLeft: "1px dotted #666",
    borderRight: "1px dotted #666",
});

export default function AccessControlList(props) {

    // meta
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const navigate = useNavigate();

    // data
    const [acl, setACL] = useState([]);
    const [groups, setGroups] = useState([]);
    const [fetchACL, setFetchACL] = useState(true);

    // filters
    const [query, setQuery] = useState("");

    // view
    const [dialogData, setDialogData] = useState({open: false});

    const onFetchACL = useCallback((force = false) => {
        if (force || fetchACL) {
            setFetchACL(false);
            props.api.fetchGroups().then(res => {
               if (!res.success) {
                   props.showDialog(res.msg, L("permissions.fetch_group_error"));
                   navigate("/admin/dashboard");
               } else {
                   setGroups(res.groups);
                   props.api.fetchPermissions().then(res => {
                       if (!res.success) {
                           props.showDialog(res.msg, L("permissions.fetch_permission_error"));
                           navigate("/admin/dashboard");
                       } else {
                           setACL(res.permissions);
                       }
                   });
               }
            });
        }
    }, [fetchACL]);

    useEffect(() => {
        onFetchACL();
    }, []);

    useEffect(() => {
        requestModules(props.api, ["general", "permissions"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onChangePermission = useCallback((methodIndex, groupId, selected) => {
        let newGroups = null;
        let currentGroups = acl[methodIndex].groups;
        if (groupId === null) {
            newGroups = [];
        } else {
            let groupIndex = currentGroups.indexOf(groupId);
            if (!selected) {
                if (currentGroups.length === 0) {
                    // it was an "everyone permission" before
                    newGroups = groups.filter(group => group.id !== groupId).map(group => group.id);
                } else if (groupIndex !== -1) {
                    newGroups = [...currentGroups];
                    newGroups.splice(groupIndex, 1);
                }
            } else if (groupIndex === -1) {
                newGroups = [...currentGroups];
                newGroups.push(groupId);
            }
        }

        if (newGroups !== null) {
            props.api.updatePermission(acl[methodIndex].method, newGroups).then((data) => {
               if (data.success) {
                   let newACL = [...acl];
                   newACL[methodIndex].groups = newGroups;
                   setACL(newACL);
                   props.api.fetchUser();
               } else {
                   props.showDialog(data.msg, L("permissions.update_permission_error"));
               }
            });
        }
    }, [acl]);

    const onDeletePermission = useCallback(method => {
        props.api.deletePermission(method).then(data => {
            if (data.success) {
                let newACL = acl.filter(acl => acl.method.toLowerCase() !== method.toLowerCase());
                setACL(newACL);
                props.api.fetchUser();
            } else {
                props.showDialog(data.msg, L("permissions.delete_permission_error"));
            }
        })
    }, [acl]);

    const onUpdatePermission = useCallback((inputData, groups) => {
        props.api.updatePermission(inputData.method, groups, inputData.description).then(data => {
            if (data.success) {
                let newACL = acl.filter(acl => acl.method.toLowerCase() !== inputData.method.toLowerCase());
                newACL.push({method: inputData.method, groups: groups, description: inputData.description});
                newACL = newACL.sort((a, b) => a.method.localeCompare(b.method))
                setACL(newACL);
                props.api.fetchUser();
            } else {
                props.showDialog(data.msg, L("permissions.update_permission_error"));
            }
        })
    }, [acl]);

    const isRestricted = (method) => {
        return ["permission/update", "permission/delete"].includes(method.toLowerCase()) ||
                !props.api.hasGroup(USER_GROUP_ADMIN);
    }

    const PermissionList = () => {
        let rows = [];

        for (let index = 0; index < acl.length; index++) {
            const permission = acl[index];

            if (query) {
                if (!permission.method.toLowerCase().includes(query.toLowerCase()) &&
                    !permission.description.toLowerCase().includes(query.toLowerCase())) {
                    continue;
                }
            }

            rows.push(
                <TableRow key={"perm-" + index}>
                    <TableCell>
                        <Grid container>
                            <Grid item xs={1} alignContent={"center"}>
                                <IconButton size={"small"} color={"primary"}
                                            disabled={isRestricted(permission.method)}
                                            onClick={() => setDialogData({
                                                open: true,
                                                title: L("permissions.edit_permission"),
                                                inputs: [
                                                    { type: "label", value: L("permissions.method") + ":" },
                                                    { type: "text", name: "method", value: permission.method, disabled: true },
                                                    { type: "label", value: L("permissions.description") + ":" },
                                                    { type: "text", name: "description", value: permission.description, maxLength: 128 }
                                                ],
                                                onOption: (option, inputData) => option === 1 ? onUpdatePermission(inputData, permission.groups) : true                                            })} >
                                    <Edit />
                                </IconButton>
                                <IconButton size={"small"} color={"error"}
                                            disabled={isRestricted(permission.method)}
                                            onClick={() => setDialogData({
                                                open: true,
                                                title: L("permissions.delete_permission_confirm"),
                                                message: L("permissions.method") + ": " + permission.method,
                                                onOption: (option) => option === 1 ? onDeletePermission(permission.method) : true
                                            })} >
                                    <Delete />
                                </IconButton>
                            </Grid>
                            <Grid item>
                                <b>{permission.method}</b><br />
                                <i>{permission.description}</i>
                            </Grid>
                        </Grid>
                    </TableCell>
                    <BorderedColumn key={"perm-" + index + "-everyone"} align={"center"}>
                        <Checkbox checked={!permission.groups.length}
                                  onChange={(e) => onChangePermission(index, null, e.target.checked)}
                                  disabled={isRestricted(permission.method)} />
                    </BorderedColumn>
                    {groups.map(group => <TableCell key={"perm-" + index + "-group-" + group.id} align={"center"}>
                        <Checkbox checked={permission.groups.includes(group.id)}
                                  onChange={(e) => onChangePermission(index, group.id, e.target.checked)}
                                  disabled={isRestricted(permission.method)} />
                    </TableCell>)}
                </TableRow>
            );
        }

        return <>{rows}</>
    }

    return <ViewContent title={L("permissions.title")} path={[
        <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
        <span key={"permissions"}>{L("permissions.title_short")}</span>,
    ]}>
        <Grid container>
            <Grid item xs={6}>
                <FormGroup>
                    <FormLabel>{L("permissions.search")}</FormLabel>
                    <TextField
                        placeholder={L("permissions.query") + "…"}
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        variant={"outlined"}
                        size={"small"}/>
                </FormGroup>
            </Grid>
            <Grid item xs={6} textAlign={"end"}>
                <Box>
                    <FormLabel>{L("general.controls")}</FormLabel>
                </Box>
                <ButtonBar mb={2}>
                    <Button variant={"outlined"} color={"primary"} size={"small"}
                            startIcon={<Refresh />} onClick={() => onFetchACL(true)}>
                        {L("general.reload")}
                    </Button>
                    <Button variant={"outlined"} startIcon={<Add />} size={"small"} color={"success"}
                            disabled={!props.api.hasGroup(USER_GROUP_ADMIN)}
                            onClick={() => setDialogData({
                                open: true,
                                title: L("permissions.add_permission"),
                                inputs: [
                                    {type: "label", value: L("permissions.method") + ":"},
                                    {type: "text", name: "method", value: "", placeholder: L("permissions.method")},
                                    {type: "label", value: L("permissions.description") + ":"},
                                    {
                                        type: "text",
                                        name: "description",
                                        maxLength: 128,
                                        placeholder: L("permissions.description")
                                    }
                                ],
                                onOption: (option, inputData) => option === 1 ? onUpdatePermission(inputData, []) : true
                            })}>
                        {L("general.add")}
                    </Button>
                </ButtonBar>
            </Grid>
        </Grid>
        <TableContainer component={Paper} style={{overflowX: "initial"}}>
            <Table stickyHeader size={"small"}>
                <TableHead>
                    <TableRow>
                        <TableCell>{L("permissions.permission")}</TableCell>
                        <BorderedColumn align={"center"}><i>{L("permissions.everyone")}</i></BorderedColumn>
                        {groups.map(group => <TableCell key={"group-" + group.id} align={"center"}>
                            {group.name}
                        </TableCell>)}
                    </TableRow>
                </TableHead>
                <TableBodyStriped>
                    <PermissionList/>
                </TableBodyStriped>
            </Table>
        </TableContainer>
        <Dialog show={dialogData.open}
                onClose={() => setDialogData({open: false})}
                title={dialogData.title}
                message={dialogData.message}
                onOption={dialogData.onOption}
                inputs={dialogData.inputs}
                options={[L("general.cancel"), L("general.ok")]}/>
    </ViewContent>
}
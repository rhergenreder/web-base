import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {Link, useNavigate} from "react-router-dom";
import {Button, Checkbox, TextField, Paper, Table, TableBody, TableCell, TableContainer, TableHead, TableRow} from "@material-ui/core";
import {Add, Refresh} from "@material-ui/icons";
import {USER_GROUP_ADMIN} from "shared/constants";


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

    const onFetchACL = useCallback((force = false) => {
        if (force || fetchACL) {
            setFetchACL(false);
            props.api.fetchGroups().then(res => {
               if (!res.success) {
                   props.showDialog(res.msg, "Error fetching groups");
                   navigate("/admin/dashboard");
               } else {
                   setGroups(res.groups);
                   props.api.fetchPermissions().then(res => {
                       if (!res.success) {
                           props.showDialog(res.msg, "Error fetching permissions");
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
        requestModules(props.api, ["general"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onChangePermission = useCallback((methodIndex, groupId, selected) => {
        let newGroups = null;
        let currentGroups = acl[methodIndex].groups;
        let groupIndex = currentGroups.indexOf(groupId);
        if (!selected) {
            if (currentGroups.length === 0) {
                // it was an "everyone permission" before
                newGroups = groups.filter(group => group.id !== groupId).map(group => group.id);
            } else if (groupIndex !== -1 && currentGroups.length > 1) {
                newGroups = [...currentGroups];
                newGroups.splice(groupIndex, 1);
            }
        } else if (groupIndex === -1) {
            newGroups = [...currentGroups];
            newGroups.push(groupId);
        }

        if (newGroups !== null) {
            props.api.updatePermission(acl[methodIndex].method, newGroups).then((data) => {
               if (data.success) {
                   let newACL = [...acl];
                   newACL[methodIndex].groups = newGroups;
                   setACL(newACL);
               } else {
                   props.showDialog("Error updating permission: " + data.msg);
               }
            });
        }
    }, [acl]);

    const isRestricted = (method) => {
        return ["permissions/update", "permissions/delete"].includes(method.toLowerCase());
    }

    const PermissionList = () => {
        let rows = [];

        for (let index = 0; index < acl.length; index++) {
            const permission = acl[index];

            if (query) {
                if (!permission.method.toLowerCase().includes(query.toLowerCase()) ||
                    !permission.description.toLowerCase().includes(query.toLowerCase())) {
                    continue;
                }
            }

            rows.push(
                <TableRow key={"perm-" + index}>
                    <TableCell>
                        <b>{permission.method}</b><br />
                        <i>{permission.description}</i>
                    </TableCell>
                    {groups.map(group => <TableCell key={"perm-" + index + "-group-" + group.id} align={"center"}>
                        <Checkbox checked={!permission.groups.length || permission.groups.includes(group.id)}
                                  onChange={(e) => onChangePermission(index, group.id, e.target.checked)}
                                  disabled={isRestricted(permission.method) || !props.api.hasGroup(USER_GROUP_ADMIN)} />
                    </TableCell>)}
                </TableRow>
            );
        }

        return <>{rows}</>
    }

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>Access Control List</h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">ACL</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div className={"row"}>
            <div className={"col-6"}>
                <div className={"form-group"}>
                    <label>{L("query")}</label>
                    <TextField
                        className={"form-control"}
                        placeholder={L("search_query") + "…"}
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        variant={"outlined"}
                        size={"small"} />
                </div>
            </div>
            <div className={"col-6 text-right"}>
                <label>{L("general.controls")}</label>
                <div className={"form-group"}>
                    <Button variant={"outlined"} color={"primary"} className={"m-1"} startIcon={<Refresh />} onClick={() => onFetchACL(true)}>
                        {L("general.reload")}
                    </Button>
                    <Button variant={"outlined"} className={"m-1"} startIcon={<Add />} disabled={!props.api.hasGroup(USER_GROUP_ADMIN)}
                            onClick={() => navigate("/admin/acl/new")}>
                        {L("general.add")}
                    </Button>
                </div>
            </div>
        </div>
        <div>
            <TableContainer component={Paper}>
                <Table size={"small"}>
                    <TableHead>
                        <TableRow>
                            <TableCell sx={{width: "auto"}}>{L("permission")}</TableCell>
                            { groups.map(group => <TableCell key={"group-" + group.id} align={"center"}>
                                {group.name}
                            </TableCell>) }
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        <PermissionList />
                    </TableBody>
                </Table>
            </TableContainer>
        </div>
    </>
}
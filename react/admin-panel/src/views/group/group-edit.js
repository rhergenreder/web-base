import {useCallback, useContext, useEffect, useState} from "react";
import {Link, useNavigate, useParams} from "react-router-dom";
import {LocaleContext} from "shared/locale";
import SearchField from "shared/elements/search-field";
import React from "react";
import {ControlsColumn, DataTable, NumericColumn, StringColumn} from "shared/elements/data-table";
import EditIcon from "@mui/icons-material/Edit";
import usePagination from "shared/hooks/pagination";
import Dialog from "shared/elements/dialog";
import {FormControl, FormGroup, FormLabel, styled, TextField, Button, CircularProgress} from "@mui/material";
import {Add, Delete, KeyboardArrowLeft, Save} from "@mui/icons-material";
import {MuiColorInput} from "mui-color-input";
import ButtonBar from "../../elements/button-bar";

const defaultGroupData = {
    name: "",
    color: "#ccc",
    members: []
};

export default function EditGroupView(props) {

    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);

    const { groupId } = useParams();
    const navigate = useNavigate();
    const isNewGroup = groupId === "new";
    const pagination = usePagination();
    const api = props.api;

    // data
    const [fetchGroup, setFetchGroup] = useState(!isNewGroup);
    const [group, setGroup] = useState(isNewGroup ? defaultGroupData : null);
    const [members, setMembers] = useState([]);
    const [selectedUser, setSelectedUser] = useState(null);

    // ui
    const [dialogData, setDialogData] = useState({open: false});
    const [isSaving, setSaving] = useState(false);

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog(data.msg, "Error fetching localization");
            }
        });
    }, [currentLocale]);

    const onFetchGroup = useCallback((force = false) => {
        if (force || fetchGroup) {
            setFetchGroup(false);
            api.getGroup(groupId).then(res => {
               if (!res.success) {
                   props.showDialog(res.msg, "Error fetching group");
                   navigate("/admin/groups");
               } else {
                   setGroup(res.group);
               }
            });
        }
    }, [api, fetchGroup]);

    const onFetchMembers = useCallback(async (page, count, orderBy, sortOrder) => {
        api.fetchGroupMembers(groupId, page, count, orderBy, sortOrder).then((res) => {
            if (res.success) {
                setMembers(res.users);
                pagination.update(res.pagination);
            } else {
                props.showDialog(res.msg, L("account.fetch_group_members_error"));
                return null;
            }
        });
    }, [groupId, api, pagination]);

    const onRemoveMember = useCallback(userId => {
        api.removeGroupMember(groupId, userId).then(data => {
            if (data.success) {
                let newMembers = members.filter(u => u.id !== userId);
                setMembers(newMembers);
            } else {
                props.showDialog(data.msg, L("account.remove_group_member_error"));
            }
        });
    }, [api, groupId, members]);

    const onAddMember = useCallback(() => {
        if (selectedUser) {
            api.addGroupMember(groupId, selectedUser.id).then(data => {
                if (!data.success) {
                    props.showDialog(data.msg, L("account.add_group_member_error"));
                } else {
                    let newMembers = [...members];
                    newMembers.push(selectedUser);
                    setMembers(newMembers);
                }
                setSelectedUser(null);
            });
        }
    }, [api, groupId, selectedUser])

    const onSave = useCallback(() => {
        setSaving(true);
        if (isNewGroup) {
            api.createGroup(group.name, group.color).then(data => {
                setSaving(false);
                if (!data.success) {
                   props.showDialog(data.msg, L("account.create_group_error"));
               } else {
                   navigate(`/admin/group/${data.id}`)
               }
            });
        } else {
            api.updateGroup(groupId, group.name, group.color).then(data => {
                setSaving(false);
                if (!data.success) {
                    props.showDialog(data.msg, L("account.update_group_error"));
                }
            });
        }
    }, [api, groupId, isNewGroup, group]);

    const onSearchUser = useCallback((async (query) => {
        let data = await api.searchUser(query);
        if (!data.success) {
            props.showDialog(data.msg, L("account.search_users_error"));
            return [];
        }

        return data.users;
    }), [api]);

    const onDeleteGroup = useCallback(() => {
        api.deleteGroup(groupId).then(data => {
           if (!data.success) {
               props.showDialog(data.msg, L("account.delete_group_error"));
           } else {
               navigate("/admin/groups");
           }
        });
    }, [api, groupId]);

    const onOpenMemberDialog = useCallback(() => {
        setDialogData({
            open: true,
            title: L("account.add_group_member_title"),
            message: L("account.add_group_member_text"),
            inputs: [
                {
                    type: "custom", name: "search", element: SearchField,
                    size: "small", key: "search",
                    onSearch: v => onSearchUser(v),
                    onSelect: u => setSelectedUser(u),
                    displayText: u => u.fullName || u.name
                }
            ],
            onOption: (option) => option === 0 ? onAddMember() : setSelectedUser(null)
        });
    }, []);

    useEffect(() => {
        onFetchGroup();
    }, []);

    const complementaryColor = (color) => {
        if (color.startsWith("#")) {
            color = color.substring(1);
        }

        let numericValue = parseInt(color, 16);
        return "#" + (0xFFFFFF - numericValue).toString(16);
    }

    if (group === null) {
        return <CircularProgress />
    }

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>
                            { isNewGroup ? L("account.new_group") : L("account.group") + ": " + group.name }
                        </h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active"><Link to={"/admin/groups"}>{L("account.group")}</Link></li>
                            <li className="breadcrumb-item active">{ isNewGroup ? L("general.new") : groupId }</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div className={"content"}>
            <div className={"row"}>
                <div className={"col-4 pl-5 pr-5"}>
                    <FormGroup className={"my-2"}>
                        <FormLabel htmlFor={"name"}>
                            {L("account.group_name")}
                        </FormLabel>
                        <FormControl>
                            <TextField maxLength={32} value={group.name}
                                       size={"small"} name={"name"}
                                       placeholder={L("account.name")}
                                       onChange={e => setGroup({...group, name: e.target.value})}/>
                        </FormControl>
                    </FormGroup>

                    <FormGroup className={"my-2"}>
                        <FormLabel htmlFor={"color"}>
                            {L("account.color")}
                        </FormLabel>
                        <FormControl>
                            <MuiColorInput
                                format={"hex"}
                                value={group.color}
                                size={"small"}
                                variant={"outlined"}
                                onChange={color => setGroup({...group, color: color})}
                            />
                        </FormControl>
                    </FormGroup>

                    <ButtonBar mt={2}>
                        <Button startIcon={<KeyboardArrowLeft />}
                                variant={"outlined"}
                                onClick={() => navigate("/admin/groups")}>
                            {L("general.go_back")}
                        </Button>
                        <Button startIcon={isSaving ? <CircularProgress size={14} /> : <Save />}
                                color={"primary"}
                                variant={"outlined"}
                                disabled={isSaving || (!api.hasPermission(isNewGroup ? "groups/create" : "groups/update"))}
                                onClick={onSave}>
                            {isSaving ? L("general.saving") + "…" : L("general.save")}
                        </Button>
                        { !isNewGroup &&
                            <Button startIcon={<Delete/>} disabled={!api.hasPermission("groups/delete")}
                                    variant={"outlined"} color={"secondary"}
                                    onClick={() => setDialogData({
                                        open: true,
                                        title: L("account.delete_group_title"),
                                        message: L("account.delete_group_text"),
                                        onOption: option => option === 0 && onDeleteGroup()
                                    })}>
                                {L("general.delete")}
                            </Button>
                        }
                    </ButtonBar>
                </div>
            </div>
            {!isNewGroup && api.hasPermission("groups/getMembers") ?
                <div className={"m-3 col-6"}>
                    <h4>{L("account.members")}</h4>
                    <DataTable
                        data={members}
                        pagination={pagination}
                        defaultSortOrder={"asc"}
                        defaultSortColumn={0}
                        className={"table table-striped"}
                        fetchData={onFetchMembers}
                        placeholder={L("account.no_members")}
                        columns={[
                            new NumericColumn(L("general.id"), "id"),
                            new StringColumn(L("account.name"), "name"),
                            new StringColumn(L("account.full_name"), "fullName"),
                            new ControlsColumn(L("general.controls"), [
                                {
                                    label: L("general.edit"),
                                    element: EditIcon,
                                    onClick: (entry) => navigate(`/admin/user/${entry.id}`)
                                },
                                {
                                    label: L("general.remove"),
                                    element: Delete,
                                    disabled: !api.hasPermission("groups/removeMember"),
                                    color: "secondary",
                                    onClick: (entry) => setDialogData({
                                        open: true,
                                        title: L("account.remove_group_member_title"),
                                        message: sprintf(L("account.remove_group_member_text"), entry.fullName || entry.name),
                                        onOption: (option) => option === 0 && onRemoveMember(entry.id)
                                    })
                                }
                            ]),
                        ]}
                        buttons={[{
                            key: "btn-add-member",
                            color: "primary",
                            startIcon: <Add />,
                            disabled: !api.hasPermission("groups/addMember"),
                            children: L("general.add"),
                            onClick: onOpenMemberDialog
                        }]}
                    />
                </div>
                : <></>
            }
        </div>
        <Dialog show={dialogData.open}
                onClose={() => setDialogData({open: false})}
                title={dialogData.title}
                message={dialogData.message}
                onOption={dialogData.onOption}
                inputs={dialogData.inputs}
                options={[L("general.ok"), L("general.cancel")]} />
    </>

}
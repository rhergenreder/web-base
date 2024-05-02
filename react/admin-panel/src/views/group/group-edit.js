import {useCallback, useContext, useEffect, useRef, useState} from "react";
import {Link, useNavigate, useParams} from "react-router-dom";
import {LocaleContext} from "shared/locale";
import SearchField from "shared/elements/search-field";
import React from "react";
import {sprintf} from "sprintf-js";
import {DataTable, ControlsColumn, NumericColumn, StringColumn} from "shared/elements/data-table";
import EditIcon from "@mui/icons-material/Edit";
import usePagination from "shared/hooks/pagination";
import Dialog from "shared/elements/dialog";
import {FormControl, FormLabel, TextField, Button, CircularProgress, Box, Grid} from "@mui/material";
import {Add, Delete, KeyboardArrowLeft, Save} from "@mui/icons-material";
import {MuiColorInput} from "mui-color-input";
import ButtonBar from "../../elements/button-bar";
import ViewContent from "../../elements/view-content";
import FormGroup from "../../elements/form-group";

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
    const showDialog = props.showDialog;

    // data
    const [fetchGroup, setFetchGroup] = useState(!isNewGroup);
    const [group, setGroup] = useState(isNewGroup ? defaultGroupData : null);
    const [members, setMembers] = useState([]);
    const selectedUserRef = useRef(null);

    // ui
    const [dialogData, setDialogData] = useState({open: false});
    const [isSaving, setSaving] = useState(false);

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                showDialog(data.msg, "Error fetching localization");
            }
        });
    }, [currentLocale]);

    const onFetchGroup = useCallback((force = false) => {
        if (force || fetchGroup) {
            setFetchGroup(false);
            api.getGroup(groupId).then(res => {
               if (!res.success) {
                   showDialog(res.msg, "Error fetching group");
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
                showDialog(res.msg, L("account.fetch_group_members_error"));
                return null;
            }
        });
    }, [api, showDialog, pagination, groupId]);

    const onRemoveMember = useCallback(userId => {
        api.removeGroupMember(groupId, userId).then(data => {
            if (data.success) {
                let newMembers = members.filter(u => u.id !== userId);
                setMembers(newMembers);
            } else {
                showDialog(data.msg, L("account.remove_group_member_error"));
            }
        });
    }, [api, showDialog, groupId, members]);

    const onAddMember = useCallback(() => {
        if (selectedUserRef.current) {
            api.addGroupMember(groupId, selectedUserRef.current.id).then(data => {
                if (!data.success) {
                    showDialog(data.msg, L("account.add_group_member_error"));
                } else {
                    let newMembers = [...members];
                    newMembers.push(selectedUserRef.current);
                    setMembers(newMembers);
                }
                selectedUserRef.current = null;
            });
        }
    }, [api, showDialog, groupId, selectedUserRef, members])

    const onSave = useCallback(() => {
        setSaving(true);
        if (isNewGroup) {
            api.createGroup(group.name, group.color).then(data => {
                setSaving(false);
                if (!data.success) {
                   showDialog(data.msg, L("account.create_group_error"));
               } else {
                   navigate(`/admin/group/${data.id}`)
               }
            });
        } else {
            api.updateGroup(groupId, group.name, group.color).then(data => {
                setSaving(false);
                if (!data.success) {
                    showDialog(data.msg, L("account.update_group_error"));
                }
            });
        }
    }, [api, showDialog, groupId, isNewGroup, group]);

    const onSearchUser = useCallback((async (query) => {
        let data = await api.searchUser(query);
        if (!data.success) {
            showDialog(data.msg, L("account.search_users_error"));
            return [];
        }

        return data.users;
    }), [api, showDialog]);

    const onDeleteGroup = useCallback(() => {
        api.deleteGroup(groupId).then(data => {
           if (!data.success) {
               showDialog(data.msg, L("account.delete_group_error"));
           } else {
               navigate("/admin/groups");
           }
        });
    }, [api, showDialog, groupId]);

    const onOpenMemberDialog = useCallback(() => {
        setDialogData({
            open: true,
            title: L("account.add_group_member_title"),
            message: L("account.add_group_member_text"),
            inputs: [
                {
                    type: "custom", name: "search",
                    size: "small", key: "search",
                    element: SearchField,
                    onSearch: v => onSearchUser(v),
                    onSelect: u => { selectedUserRef.current = u },
                    getOptionLabel: u => u.fullName || u.name
                }
            ],
            onOption: (option) => {
                if(option === 1) {
                    onAddMember()
                } else {
                    selectedUserRef.current = null
                }
            }
        });
    }, [onAddMember, onSearchUser, selectedUserRef, setDialogData]);

    useEffect(() => {
        onFetchGroup();
    }, []);

    if (group === null) {
        return <CircularProgress />
    }

    return <>
    <ViewContent title={ isNewGroup ? L("account.new_group") : L("account.group") + ": " + group.name }
        path={[
            <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
            <Link key={"group"} to={"/admin/groups"}>{L("account.group")}</Link>,
            <span key={"action"} >{isNewGroup ? L("general.new") : groupId}</span>,
        ]}>
        <Grid container>
            <Grid item xs={6}>
                <FormGroup>
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

                <FormGroup>
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
                            variant={"outlined"} color={"error"}
                            onClick={() => setDialogData({
                                open: true,
                                title: L("account.delete_group_title"),
                                message: L("account.delete_group_text"),
                                onOption: option => option === 1 ? onDeleteGroup() : true
                            })}>
                        {L("general.delete")}
                    </Button>
                }
            </ButtonBar>
            {!isNewGroup && api.hasPermission("groups/getMembers") ?
                <Box mt={3}>
                    <h4>{L("account.members")}</h4>
                    <DataTable
                        data={members}
                        pagination={pagination}
                        defaultSortOrder={"asc"}
                        defaultSortColumn={0}
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
                                    color: "error",
                                    onClick: (entry) => setDialogData({
                                        open: true,
                                        title: L("account.remove_group_member_title"),
                                        message: sprintf(L("account.remove_group_member_text"), entry.fullName || entry.name),
                                        onOption: (option) => option === 1 ? onRemoveMember(entry.id) : true
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
                </Box>
                : <></>
            }
                </Grid>
            </Grid>
        </ViewContent>
        <Dialog show={dialogData.open}
                onClose={() => setDialogData({open: false})}
                title={dialogData.title}
                message={dialogData.message}
                onOption={dialogData.onOption}
                inputs={dialogData.inputs}
                options={[L("general.cancel"), L("general.ok")]} />
     </>
}
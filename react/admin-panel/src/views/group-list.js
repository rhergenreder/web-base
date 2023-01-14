import {Link, useNavigate} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {DataColumn, DataTable, NumericColumn, StringColumn} from "shared/elements/data-table";
import {Button, IconButton} from "@material-ui/core";
import EditIcon from '@mui/icons-material/Edit';
import AddIcon from '@mui/icons-material/Add';


export default function GroupListView(props) {

    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const navigate = useNavigate();

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                alert(data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchGroups = useCallback(async (page, count, orderBy, sortOrder) => {
        let res = await props.api.fetchGroups(page, count, orderBy, sortOrder);
        if (res.success) {
            return Promise.resolve([res.groups, res.pagination]);
        } else {
            props.showAlert("Error fetching groups", res.msg);
            return null;
        }
    }, []);

    const actionColumn = (() => {
        let column = new DataColumn(L("general.actions"), null, false);
        column.renderData = (L, entry) => <>
            <IconButton size={"small"} title={L("general.edit")} onClick={() => navigate("/admin/group/" + entry.id)}>
                <EditIcon />
            </IconButton>
        </>
        return column;
    })();

    const columnDefinitions = [
        new NumericColumn(L("general.id"), "id"),
        new StringColumn(L("group.name"), "name"),
        new NumericColumn(L("group.member_count"), "memberCount"),
        actionColumn,
    ];

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>Users</h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">Groups</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                <div className={"container-fluid"}>
                    <Link to="/admin/group/new">
                        <Button variant={"outlined"} startIcon={<AddIcon />} size={"small"}>
                            {L("general.create_new")}
                        </Button>
                    </Link>
                    <DataTable className={"table table-striped"}
                               fetchData={onFetchGroups}
                               placeholder={"No groups to display"}
                               columns={columnDefinitions} />
                </div>
            </div>
        </div>
    </>
}
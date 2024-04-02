import {Link, useNavigate} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {ControlsColumn, DataColumn, DataTable, NumericColumn, StringColumn} from "shared/elements/data-table";
import {Button, IconButton} from "@material-ui/core";
import EditIcon from '@mui/icons-material/Edit';
import AddIcon from '@mui/icons-material/Add';
import usePagination from "shared/hooks/pagination";


export default function GroupListView(props) {

    // meta
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const navigate = useNavigate();
    const pagination = usePagination();
    const api = props.api;

    // data
    const [groups, setGroups] = useState([]);

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog(data.msg, "Error fetching localization");
            }
        });
    }, [currentLocale]);

    const onFetchGroups = useCallback(async (page, count, orderBy, sortOrder) => {
        api.fetchGroups(page, count, orderBy, sortOrder).then((res) => {
            if (res.success) {
                setGroups(res.groups);
                pagination.update(res.pagination);
            } else {
                props.showDialog(res.msg, "Error fetching groups");
                return null;
            }
        });
    }, [api, pagination]);

    const columnDefinitions = [
        new NumericColumn(L("general.id"), "id"),
        new StringColumn(L("account.name"), "name"),
        new NumericColumn(L("account.member_count"), "memberCount", { align: "center" }),
        new ControlsColumn(L("general.controls"), [
            { label: L("general.edit"), element: EditIcon, onClick: (entry) => navigate(`/admin/group/${entry.id}`) }
        ]),
    ];

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">{L("account.groups")}</li>
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
                    <DataTable
                        data={groups}
                        pagination={pagination}
                        defaultSortOrder={"asc"}
                        defaultSortColumn={0}
                        className={"table table-striped"}
                        fetchData={onFetchGroups}
                        placeholder={"No groups to display"}
                        title={L("account.groups")}
                        columns={columnDefinitions} />
                </div>
            </div>
        </div>
    </>
}
import {Link, useNavigate} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {ControlsColumn, DataTable, NumericColumn, StringColumn} from "shared/elements/data-table";
import {Add, Edit} from "@mui/icons-material";
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
            { label: L("general.edit"), element: Edit, onClick: (entry) => navigate(`/admin/group/${entry.id}`) }
        ]),
    ];

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>{L("account.groups")}</h1>
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
                    <DataTable
                        data={groups}
                        pagination={pagination}
                        defaultSortOrder={"asc"}
                        defaultSortColumn={0}
                        className={"table table-striped"}
                        fetchData={onFetchGroups}
                        placeholder={"No groups to display"}
                        columns={columnDefinitions}
                        buttons={[{
                            key: "btn-create-group",
                            color: "primary",
                            startIcon: <Add />,
                            onClick: () => navigate("/admin/group/new"),
                            disabled: !api.hasPermission("groups/create"),
                            children: L("general.create_new")
                        }]}/>
                </div>
            </div>
        </div>
    </>
}
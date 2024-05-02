import {Link, useNavigate} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {ControlsColumn, DataTable, NumericColumn, StringColumn} from "shared/elements/data-table";
import {Add, Edit} from "@mui/icons-material";
import usePagination from "shared/hooks/pagination";
import ViewContent from "../../elements/view-content";


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

    return <ViewContent title={L("account.groups")} path={[
        <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
        <span key={"groups"} >{L("account.groups")}</span>,
    ]}>
        <DataTable
            data={groups}
            pagination={pagination}
            defaultSortOrder={"asc"}
            defaultSortColumn={0}
            fetchData={onFetchGroups}
            placeholder={"No groups to display"}
            columns={columnDefinitions}
            buttons={[{
                key: "btn-create-group",
                color: "success",
                startIcon: <Add />,
                onClick: () => navigate("/admin/group/new"),
                disabled: !api.hasPermission("groups/create"),
                children: L("general.create_new")
            }]}/>
    </ViewContent>
}
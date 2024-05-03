import {Link, useNavigate} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    BoolColumn,
    ControlsColumn,
    DataColumn,
    DataTable, DateTimeColumn,
    NumericColumn,
    StringColumn
} from "shared/elements/data-table";
import {Box, Chip} from "@mui/material";
import {Edit, Add} from "@mui/icons-material";
import usePagination from "shared/hooks/pagination";
import ViewContent from "../../elements/view-content";
import ProfilePicture from "shared/elements/profile-picture";
import ProfileLink from "../../elements/profile-link";

export default function UserListView(props) {

    const api = props.api;
    const showDialog = props.showDialog;
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const navigate = useNavigate();
    const pagination = usePagination();
    const [users, setUsers] = useState([]);

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchUsers = useCallback((page, count, orderBy, sortOrder) => {
        api.fetchUsers(page, count, orderBy, sortOrder).then((res) => {
            if (res.success) {
                setUsers(res.users);
                pagination.update(res.pagination);
            } else {
                showDialog(res.msg, "Error fetching users");
                return null;
            }
        });
    }, [api, showDialog]);

    const groupColumn = (() => {
       let column = new DataColumn(L("account.groups"), "groups");
       column.renderData = (L, entry) => {
           return Object.values(entry.groups).map(group => <Chip
               key={"group-" + group.id}
               style={{ backgroundColor: group.color }}
               label={group.name} />
           )
       }
       return column;
    })();

    const nameColumn = (() => {
       let column = new DataColumn(L("account.username"), "name");
        column.renderData = (L, entry) => {
            return <ProfileLink user={entry} text={entry.name} size={30}
                                onClick={() => navigate("/admin/user/" + entry.id)}/>
        }
        return column;
    })();

    const columnDefinitions = [
        new NumericColumn(L("general.id"), "id"),
        nameColumn,
        new StringColumn(L("account.full_name"), "fullName"),
        new StringColumn(L("account.email"), "email"),
        groupColumn,
        new DateTimeColumn(L("account.registered_at"), "registeredAt"),
        new BoolColumn(L("account.active"), "active", { align: "center" }),
        new BoolColumn(L("account.confirmed"), "confirmed", { align: "center" }),
        new ControlsColumn(L("general.controls"), [
            { label: L("general.edit"), element: Edit, onClick: (entry) => navigate(`/admin/user/${entry.id}`) }
        ]),
    ];

    return <ViewContent title={L("account.users")} path={[
        <Link key={"home"} to={"/admin/dashboard"}>Home</Link>,
        <span key={"users"}>{L("account.users")}</span>
    ]}>
        <DataTable
            data={users}
            pagination={pagination}
            defaultSortOrder={"asc"}
            defaultSortColumn={0}
            fetchData={onFetchUsers}
            placeholder={L("account.user_list_placeholder")}
            columns={columnDefinitions}
            buttons={[{
                key: "btn-create",
                color: "success",
                startIcon: <Add />,
                children: L("general.create_new"),
                disabled: !api.hasPermission("user/create") && !api.hasPermission("user/invite"),
                onClick: () => navigate("/admin/user/new")
            }]}/>
    </ViewContent>
}